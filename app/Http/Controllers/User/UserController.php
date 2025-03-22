<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserTransfer;
use App\Http\Requests\User\UserUpdate;
use App\Http\Requests\User\UserChangePassword;
use App\Services\AuthService;
use App\Services\UserService;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Ticket;
use App\Utils\Helper;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function getActiveSession(Request $request)
    {
        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        $authService = new AuthService($user);
        return response([
            'data' => $authService->getSessions()
        ]);
    }

    public function removeActiveSession(Request $request)
    {
        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        $authService = new AuthService($user);
        return response([
            'data' => $authService->removeSession($request->input('session_id'))
        ]);
    }

    public function checkLogin(Request $request)
    {
        $data = [
            'is_login' => $request->user['id'] ? true : false
        ];
        if ($request->user['is_admin']) {
            $data['is_admin'] = true;
        }
        return response([
            'data' => $data
        ]);
    }

    public function changePassword(UserChangePassword $request)
    {
        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $request->input('old_password'),
            $user->password)
        ) {
            abort(500, __('The old password is wrong'));
        }
        $user->password = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        $user->password_salt = NULL;
        if (!$user->save()) {
            abort(500, __('Save failed'));
        }
        return response([
            'data' => true
        ]);
    }

    public function info(Request $request)
    {
        $user = User::where('id', $request->user['id'])
            ->select([
                'email',
                'transfer_enable',
                'last_login_at',
                'created_at',
                'banned',
                'remind_expire',
                'remind_traffic',
                'expired_at',
                'balance',
                'commission_balance',
                'plan_id',
                'discount',
                'commission_rate',
                'telegram_id',
                'uuid'
            ])
            ->first();
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        $user['avatar_url'] = 'https://cdn.v2ex.com/gravatar/' . md5($user->email) . '?s=64&d=identicon';
        return response([
            'data' => $user
        ]);
    }

    public function getStat(Request $request)
    {
        $stat = [
            Order::where('status', 0)
                ->where('user_id', $request->user['id'])
                ->count(),
            Ticket::where('status', 0)
                ->where('user_id', $request->user['id'])
                ->count(),
            User::where('invite_user_id', $request->user['id'])
                ->count()
        ];
        return response([
            'data' => $stat
        ]);
    }

    public function getSubscribe(Request $request)
    {
        $user = User::where('id', $request->user['id'])
            ->select([
                'plan_id',
                'token',
                'expired_at',
                'u',
                'd',
                'transfer_enable',
                'email',
                'uuid'
            ])
            ->first();
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        if ($user->plan_id) {
            $user['plan'] = Plan::find($user->plan_id);
            if (!$user['plan']) {
                abort(500, __('Subscription plan does not exist'));
            }
        }
        $user['subscribe_url'] = Helper::getSubscribeUrl("/api/v1/client/subscribe?token={$user['token']}");
        $userService = new UserService();
        $user['reset_day'] = $userService->getResetDay($user);
        return response([
            'data' => $user
        ]);
    }

    public function resetSecurity(Request $request)
    {
        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        if (!$user->save()) {
            abort(500, __('Reset failed'));
        }
        return response([
            'data' => Helper::getSubscribeUrl('/api/v1/client/subscribe?token=' . $user->token)
        ]);
    }

    public function update(UserUpdate $request)
    {
        $updateData = $request->only([
            'remind_expire',
            'remind_traffic'
        ]);

        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        try {
            $user->update($updateData);
        } catch (\Exception $e) {
            abort(500, __('Save failed'));
        }

        return response([
            'data' => true
        ]);
    }

    public function transfer(UserTransfer $request)
    {
        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, __('The user does not exist'));
        }
        if ($request->input('transfer_amount') > $user->commission_balance) {
            abort(500, __('Insufficient commission balance'));
        }
        $user->commission_balance = $user->commission_balance - $request->input('transfer_amount');
        $user->balance = $user->balance + $request->input('transfer_amount');
        if (!$user->save()) {
            abort(500, __('Transfer failed'));
        }
        return response([
            'data' => true
        ]);
    }

    public function getQuickLoginUrl(Request $request)
    {
        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, __('The user does not exist'));
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user->id, 60);
        $redirect = '/#/login?verify=' . $code . '&redirect=' . ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
        if (config('v2board.app_url')) {
            $url = config('v2board.app_url') . $redirect;
        } else {
            $url = url($redirect);
        }
        return response([
            'data' => $url
        ]);
    }

    public function getCombinedInfo(Request $request)
    {
        $user = User::where('id', $request->user['id'])
            ->select(['plan_id', 'token', 'expired_at', 'u', 'd', 'transfer_enable', 'email', 'uuid'])
            ->first();

        if (!$user) {
            abort(500, __('The user does not exist'));
        }

        // 获取当前的协议和主机地址
        $baseUrl = 'https://px.bluetile.art';
        $quickLoginEndpoint = '/api/v1/user/getQuickLoginUrl';
        $quickLoginUrl = $baseUrl . $quickLoginEndpoint;

        // 从请求头获取 Authorization 令牌
        $authToken = $request->header('Authorization');

        // 使用 HTTP 客户端发送 POST 请求获取快速登录 URL
        $response = Http::withHeaders([
            'Authorization' => $authToken
        ])->post($quickLoginUrl);

        // 检查响应是否成功
        if ($response->successful()) {
            $login_url = $response->json()['data'];
        } else {
            // 如果请求失败，处理失败情况
            abort(500, __('Failed to retrieve quick login URL: ') . $response->body());
        }

        // 继续处理其他信息
        if ($user->plan_id) {
            $user['plan'] = Plan::find($user->plan_id);
            if (!$user['plan']) {
                abort(500, __('Subscription plan does not exist'));
            }
        }
        $user['subscribe_url'] = Helper::getSubscribeUrl("/api/v1/client/subscribe?token={$user['token']}");
        $userService = new UserService();
        $user['reset_day'] = $userService->getResetDay($user);

        // 返回响应，包括快速登录 URL
        return response([
            'data' => [
                'subscribe_info' => $user,
                'quick_login_url' => $login_url
            ]
        ]);
    }

    /**
     * 用户在线兑换套餐
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function redeemPlan(Request $request)
    {
        try {
            // 1. 验证请求参数
            $validated = $request->validate([
                'redeem_code' => 'required|string|size:6'
            ], [
                'redeem_code.required' => '兑换码不能为空',
                'redeem_code.string' => '兑换码必须为字符串',
                'redeem_code.size' => '兑换码长度必须为6位'
            ]);

            // 2. 获取当前用户
            $user = User::find($request->user['id']);
            if (!$user) {
                abort(500, __('用户不存在'));
            }

            // 3. 验证兑换码
            $convert = \App\Models\Convert::where('redeem_code', $validated['redeem_code'])
                ->where('end_at', '>', time())
                ->first();

            if (!$convert) {
                abort(400, '兑换码无效或已过期');
            }

            // 4. 检查兑换次数
            if ($convert->ordinal_number === -1) {
                abort(400, '该兑换码已无法使用');
            }

            // 5. 获取套餐信息
            $plan = Plan::find($convert->plan_id);
            if (!$plan) {
                abort(500, '套餐不存在');
            }

            DB::beginTransaction();
            try {
                // 6. 更新用户套餐信息
                $transfer_enable = $plan->transfer_enable * 1073741824;
                
                // 计算到期时间
                $duration = 0;
                switch ($convert->duration_unit) {
                    case 'month':
                        $duration = $convert->duration_value * 30 * 86400;
                        break;
                    case 'quarter':
                        $duration = $convert->duration_value * 90 * 86400;
                        break;
                    case 'half_year':
                        $duration = $convert->duration_value * 180 * 86400;
                        break;
                    case 'year':
                        $duration = $convert->duration_value * 365 * 86400;
                        break;
                    default:
                        $duration = $convert->duration_value * 86400;
                }

                // 更新用户信息
                $user->transfer_enable = $transfer_enable;
                $user->plan_id = $plan->id;
                $user->group_id = $plan->group_id;
                $user->expired_at = time() + $duration;
                $user->save();

                // 7. 创建订单记录
                $order = new Order();
                $order->user_id = $user->id;
                $order->plan_id = $plan->id;
                $order->trade_no = Helper::guid();
                $order->total_amount = 0;
                $order->status = 3; // 已完成
                $order->type = 4;   // 兑换
                $order->redeem_code = $validated['redeem_code'];
                $order->save();

                // 8. 更新兑换码使用次数
                if ($convert->ordinal_number > 0) {
                    if ($convert->ordinal_number === 1) {
                        $convert->ordinal_number = -1;  // 设置为已用尽
                    } else {
                        $convert->ordinal_number -= 1;
                    }
                    $convert->save();
                }

                DB::commit();

                return response([
                    'data' => [
                        'plan_name' => $plan->name,
                        'expired_at' => date('Y-m-d H:i:s', $user->expired_at),
                        'transfer_enable' => $transfer_enable,
                        'message' => '套餐兑换成功'
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                abort(500, '兑换失败：' . $e->getMessage());
            }

        } catch (\Exception $e) {
            \Log::error('套餐兑换失败:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user['id']
            ]);

            abort(500, '兑换失败：' . $e->getMessage());
        }
    }
}
