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
use App\Http\Controllers\Passport\ApiController;

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
        // 验证请求参数
        if (!$request->has('redeem_code') || empty($request->input('redeem_code'))) {
            return response([
                'data' => [
                    'state' => false,
                    'msg' => '兑换码不能为空'
                ]
            ], 400);
        }
        
        // 获取用户并验证
        $user_id = $request->user['id'];
        $user = User::find($user_id);
        if (!$user) {
            return response([
                'data' => [
                    'state' => false,
                    'msg' => '用户不存在'
                ]
            ], 404);
        }
        
        // 使用事务处理
        try {
            DB::beginTransaction();
            
            $Api = new ApiController();
            $redeemInfo = $Api->validateRedeemCode($request->input('redeem_code'));
            
            // 兑换码验证失败
            if (!$redeemInfo) {
                DB::rollBack();
                return response([
                    'data' => [
                        'state' => false,
                        'msg' => '您的兑换码有误或已被使用'
                    ]
                ], 400);
            }
            
            // 验证是否需要绑定邀请人
            if (isset($redeemInfo['is_invitation']) && $redeemInfo['is_invitation'] == 1) {
                if (!$user->invite_user_id) {
                    DB::rollBack();
                    return response([
                        'data' => [
                            'state' => false,
                            'msg' => '此兑换码需要您的账户已绑定邀请人才能使用'
                        ]
                    ], 400);
                }
            }
            
            // 验证兑换码所属者与用户邮箱是否匹配（如果兑换码有指定邮箱）
            if (isset($redeemInfo['email']) && !empty($redeemInfo['email'])) {
                if ($user->email !== $redeemInfo['email']) {
                    DB::rollBack();
                    return response([
                        'data' => [
                            'state' => false,
                            'msg' => '此兑换码已绑定其他邮箱账户，您无法使用'
                        ]
                    ], 400);
                }
            }
            
            // 尝试处理兑换
            $result = $Api->handleRedeemPlan($user, $redeemInfo);
            if (is_array($result)) {
                // 如果返回数组，说明是携带错误信息的结果
                if (!$result['success']) {
                    DB::rollBack();
                    return response([
                        'data' => [
                            'state' => false,
                            'msg' => $result['message'] ?? '兑换失败'
                        ]
                    ], 400);
                }
                
                DB::commit();
                return response([
                    'data' => [
                        'state' => true,
                        'msg' => $result['message'] ?? '兑换成功'
                    ]
                ]);
            } else if ($result === true) {
                // 兑换成功
                DB::commit();
                return response([
                    'data' => [
                        'state' => true,
                        'msg' => '兑换成功'
                    ]
                ]);
            } else {
                // 兑换失败
                DB::rollBack();
                return response([
                    'data' => [
                        'state' => false,
                        'msg' => '兑换失败，请确认兑换码是否有效'
                    ]
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('兑换码兑换失败', [
                'user_id' => $user_id,
                'code' => $request->input('redeem_code'),
                'error' => $e->getMessage()
            ]);
            
            return response([
                'data' => [
                    'state' => false,
                    'msg' => '兑换过程发生错误: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}
