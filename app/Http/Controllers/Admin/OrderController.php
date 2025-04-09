<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Passport\ApiController;
use App\Http\Requests\Admin\OrderAssign;
use App\Http\Requests\Admin\OrderUpdate;
use App\Http\Requests\Admin\OrderFetch;
use App\Models\CommissionLog;
use App\Services\OrderService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private function filter(Request $request, &$builder)
    {
        if ($request->input('filter')) {
            foreach ($request->input('filter') as $filter) {
                if ($filter['key'] === 'email') {
                    $user = User::where('email', "%{$filter['value']}%")->first();
                    if (!$user) continue;
                    $builder->where('user_id', $user->id);
                    continue;
                }
                if ($filter['condition'] === '模糊') {
                    $filter['condition'] = 'like';
                    $filter['value'] = "%{$filter['value']}%";
                }
                $builder->where($filter['key'], $filter['condition'], $filter['value']);
            }
        }
    }

    public function detail(Request $request)
    {
        $order = Order::find($request->input('id'));
        if (!$order) abort(500, '订单不存在');
        $order['commission_log'] = CommissionLog::where('trade_no', $order->trade_no)->get();
        if ($order->surplus_order_ids) {
            $order['surplus_orders'] = Order::whereIn('id', $order->surplus_order_ids)->get();
        }
        return response([
            'data' => $order
        ]);
    }

    public function fetch(OrderFetch $request)
    {
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $orderModel = Order::orderBy('created_at', 'DESC');
        if ($request->input('is_commission')) {
            $orderModel->where('invite_user_id', '!=', NULL);
            $orderModel->whereNotIn('status', [0, 2]);
            $orderModel->where('commission_balance', '>', 0);
        }
        $this->filter($request, $orderModel);
        $total = $orderModel->count();
        $res = $orderModel->forPage($current, $pageSize)
            ->get();
        $plan = Plan::get();
        for ($i = 0; $i < count($res); $i++) {
            for ($k = 0; $k < count($plan); $k++) {
                if ($plan[$k]['id'] == $res[$i]['plan_id']) {
                    $res[$i]['plan_name'] = $plan[$k]['name'];
                }
            }
        }
        return response([
            'data' => $res,
            'total' => $total
        ]);
    }

    public function paid(Request $request)
    {
        $order = Order::where('trade_no', $request->input('trade_no'))
            ->first();
        if (!$order) {
            abort(500, '订单不存在');
        }
        if ($order->status !== 0) abort(500, '只能对待支付的订单进行操作');

        $orderService = new OrderService($order);
        if (!$orderService->paid('manual_operation')) {
            abort(500, '更新失败');
        }
        return response([
            'data' => true
        ]);
    }

    public function cancel(Request $request)
    {
        $order = Order::where('trade_no', $request->input('trade_no'))
            ->first();
        if (!$order) {
            abort(500, '订单不存在');
        }
        if ($order->status !== 0) abort(500, '只能对待支付的订单进行操作');

        $orderService = new OrderService($order);
        if (!$orderService->cancel()) {
            abort(500, '更新失败');
        }
        return response([
            'data' => true
        ]);
    }

    public function update(OrderUpdate $request)
    {
        $params = $request->only([
            'commission_status'
        ]);

        $order = Order::where('trade_no', $request->input('trade_no'))
            ->first();
        if (!$order) {
            abort(500, '订单不存在');
        }

        try {
            $params['updated_at'] = time();
            //$params['commission_status'] = $request->input('commission_status');
            $order->update($params);
        } catch (\Exception $e) {
            abort(500, '更新失败');
        }

        return response([
            'data' => true
        ]);
    }

    public function assign(OrderAssign $request)
    {
        $plan = Plan::find($request->input('plan_id'));
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            abort(500, '该用户不存在');
        }

        if (!$plan) {
            abort(500, '该订阅不存在');
        }

        $userService = new UserService();
        if ($userService->isNotCompleteOrderByUserId($user->id)) {
            abort(500, '该用户还有待支付的订单，无法分配');
        }

        DB::beginTransaction();
        $order = new Order();
        $orderService = new OrderService($order);
        $order->user_id = $user->id;
        $order->plan_id = $plan->id;
        $order->period = $request->input('period');
        $order->trade_no = Helper::guid();
        $order->total_amount = $request->input('total_amount');
        $order->redeem_code='';
        if ($order->period === 'reset_price') {
            $order->type = 4;
        } else if ($user->plan_id !== NULL && $order->plan_id !== $user->plan_id) {
            $order->type = 3;
        } else if ($user->expired_at > time() && $order->plan_id == $user->plan_id) {
            $order->type = 2;
        } else {
            $order->type = 1;
        }

        $orderService->setInvite($user);

        if (!$order->save()) {
            DB::rollback();
            abort(500, '订单创建失败');
        }

        DB::commit();

        return response([
            'data' => $order->trade_no
        ]);
    }



    /**
     * 处理订单支付成功后的邀请奖励
     */
    private function handleFirstOrderReward(Order $order)
    {
        try {
            $inviteGiveType = (int)config('v2board.is_Invitation_to_give', 0);
            // 检查配置是否为模式2或3
            if ($inviteGiveType !== 2 && $inviteGiveType !== 3) {
                return;
            }

            // 获取订单用户
            $user = User::find($order->user_id);
            if (!$user || $user->first_order_reward || !$user->invite_user_id) {
                return;
            }

            // 确认是首次付费订单
            $hasOtherPaidOrders = Order::where('user_id', $user->id)
                ->where('id', '!=', $order->id)
                ->where('status', 3) // 已支付状态
                ->where('type', '!=', 4) // 排除赠送订单
                ->exists();

            if ($hasOtherPaidOrders) {
                return;
            }

            DB::transaction(function () use ($user) {
                // 标记用户已获得首单奖励
                $user->first_order_reward = 1;
                $user->save();

                // 获取邀请人信息
                $inviter = User::find($user->invite_user_id);
                if (!$inviter || (int)config('v2board.try_out_plan_id') == $inviter->plan_id) {
                    return;
                }

                // 执行邀请赠送逻辑
                $plan = Plan::find((int)config('v2board.complimentary_packages'));
                if (!$plan) {
                    return;
                }

                // 创建赠送订单
                $rewardOrder = new Order();
                $orderService = new OrderService($rewardOrder);
                $rewardOrder->user_id = $inviter->id;
                $rewardOrder->plan_id = $plan->id;
                $rewardOrder->period = 'month_price';
                $rewardOrder->trade_no = Helper::guid();
                $rewardOrder->total_amount = 0;
                $rewardOrder->status = 3;
                $rewardOrder->type = 6; // 使用不同的类型标识首单奖励
                $orderService->setInvite($user);
                $rewardOrder->save();

                // 更新邀请人有效期
                app(ApiController::class)->updateInviterExpiry($inviter, $plan, $rewardOrder);

                \Log::info('首单邀请奖励发放成功', [
                    'user_id' => $user->id,
                    'inviter_id' => $inviter->id,
                    'order_id' => $rewardOrder->id
                ]);
            });

        } catch (\Exception $e) {
            \Log::error('处理首单邀请奖励失败', [
                'error' => $e->getMessage(),
                'user_id' => $order->user_id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

}
