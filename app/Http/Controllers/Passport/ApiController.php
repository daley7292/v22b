<?php

namespace App\Http\Controllers\Passport;

use App\Http\Controllers\Passport\AuthController;
use App\Models\InviteCode;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use App\Utils\Dict;
use App\Utils\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    /**
     * 注册新用户并使用授权令牌调用两个后续的 API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // 获取当前的协议和主机地址
        $baseUrl = $request->getSchemeAndHttpHost();
        $registerEndpoint = '/api/v1/passport/auth/register';
        $registerUrl = $baseUrl . $registerEndpoint;

        // 从请求中接收 `email`、`password` 和 `code`
        $email = $request->input('email');
        $password = $request->input('password');
        $code = $request->input('code');
        $inviteCode = $request->input('invite_code');
        $emailCode = $request->input('email_code');

        // 检查必要字段是否存在
        if (!$email || !$password || !$code) {
            return response()->json([
                'status' => 'error',
                'message' => '缺少必需的参数：email、password 或 code',
            ], 400);
        }

        // 准备发送给注册 API 的请求数据
        $registerRequestData = [
            'email' => $email,
            'password' => $password,
            'invite_code' => $inviteCode,
            'email_code' => $emailCode,
        ];

        // 通过 HTTP 客户端请求注册 API
        $registerResponse = Http::post($registerUrl, $registerRequestData);

        // 检查注册请求是否成功
        if ($registerResponse->successful()) {
            // 获取注册 API 的响应数据
            $registerResponseData = $registerResponse->json();
            $authData = $registerResponseData['data']['auth_data'] ?? null;

            if ($authData) {
                // 调用第二个 API：检查优惠券
                $couponApiUrl = $baseUrl . '/api/v1/user/redemptioncode/check'; // 替换为实际的 API URL
                $couponApiResponse = Http::withHeaders([
                    'Authorization' => $authData,
                ])->post($couponApiUrl, [
                    'code' => $code,
                ]);

                if ($couponApiResponse->successful()) {
                    // 解析第二个 API 的响应内容
                    $couponData = $couponApiResponse->json()['data'];
                    $limitPlanIds = $couponData['limit_plan_ids'][0] ?? null;
                    $limitPeriod = $couponData['limit_period'][0] ?? null;

                    if ($limitPlanIds && $limitPeriod) {
                        // 调用第三个 API：保存订单
                        $orderApiUrl = $baseUrl . '/api/v1/user/order/save'; // 替换为实际的 API URL
                        $orderApiResponse = Http::withHeaders([
                            'Authorization' => $authData,
                        ])->post($orderApiUrl, [
                            'plan_id' => $limitPlanIds,
                            'period' => $limitPeriod,
                            'coupon_code' => $code,
                        ]);

                        // 检查保存订单请求是否成功
                        if ($orderApiResponse->successful()) {
                            // 获取保存订单 API 的响应数据
                            $orderResponseData = $orderApiResponse->json();
                            $tradeNo = $orderResponseData['data'] ?? null;

                            if ($tradeNo) {
                                // 调用第四个 API：结算订单
                                $checkoutApiUrl = $baseUrl . '/api/v1/user/order/checkout'; // 替换为实际的 API URL
                                $checkoutApiResponse = Http::withHeaders([
                                    'Authorization' => $authData,
                                ])->post($checkoutApiUrl, [
                                    'trade_no' => $tradeNo,
                                ]);

                                // 检查结算订单请求是否成功
                                if ($checkoutApiResponse->successful()) {
                                    // 获取结算订单 API 的响应数据
                                    $checkoutResponseData = $checkoutApiResponse->json();
                                    $checkoutSuccess = $checkoutResponseData['type'] == -1 && $checkoutResponseData['data'] === true;

                                    if ($checkoutSuccess) {
                                        // 返回完整的四次 API 结果
                                        return response()->json([
                                            'status' => 'success',
                                            'registration' => $registerResponseData,
                                            'couponCheck' => $couponApiResponse->json(),
                                            'orderSave' => $orderResponseData,
                                            'orderCheckout' => $checkoutResponseData,
                                        ]);
                                    } else {
                                        return response()->json([
                                            'status' => 'error',
                                            'message' => '订单结算失败',
                                            'details' => $checkoutApiResponse->json(),
                                        ], $checkoutApiResponse->status());
                                    }
                                } else {
                                    return response()->json([
                                        'status' => 'error',
                                        'message' => '订单结算请求失败',
                                        'details' => $checkoutApiResponse->json(),
                                    ], $checkoutApiResponse->status());
                                }
                            } else {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => '保存订单响应中缺少 trade_no',
                                ], 500);
                            }
                        } else {
                            return response()->json([
                                'status' => 'error',
                                'message' => '保存订单失败',
                                'details' => $orderApiResponse->json(),
                            ], $orderApiResponse->status());
                        }
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => '无法从优惠券检查中解析 limit_plan_ids 或 limit_period',
                        ], 500);
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => '优惠券检查失败',
                        'details' => $couponApiResponse->json(),
                    ], $couponApiResponse->status());
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => '注册响应中缺少 auth_data',
                ], 500);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => '注册失败',
                'details' => $registerResponse->json(),
            ], $registerResponse->status());
        }
    }

    public function validateCouponAndSaveOrder(Request $request)
    {
        // 获取请求中的Authorization头
        $authorization = $request->header('Authorization');

        // 验证Authorization是否存在
        if (!$authorization) {
            return response()->json([
                'status' => 'error',
                'message' => '缺少 Authorization 头',
            ], 400);
        }

        // 获取请求体中的优惠码
        $code = $request->input('code');
        if (!$code) {
            return response()->json([
                'status' => 'error',
                'message' => '缺少必需的参数：code',
            ], 400);
        }

        // 构建优惠码验证API的URL
        $baseUrl = $request->getSchemeAndHttpHost();
        $couponApiUrl = $baseUrl . '/api/v1/user/redemptioncode/check';

        // 调用优惠码验证API
        $couponApiResponse = $this->apiRequest($couponApiUrl, ['code' => $code], $authorization);

        // 检查优惠码验证结果
        if (!$couponApiResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => '优惠码验证失败',
                'details' => $couponApiResponse->json(),
            ], $couponApiResponse->status());
        }

        // 获取优惠码验证成功后的数据，如 plan_id 和 period
        $couponData = $couponApiResponse->json()['data'];
        $planId = $couponData['limit_plan_ids'][0] ?? null;
        $period = $couponData['limit_period'][0] ?? null;

        // 验证获取到的 plan_id 和 period
        if (!$planId || !$period) {
            return response()->json([
                'status' => 'error',
                'message' => '优惠码响应中缺少有效的 plan_id 或 period',
            ], 500);
        }

        // 构建保存订单API的URL
        $orderApiUrl = $baseUrl . '/api/v1/user/order/save';

        // 调用保存订单API
        $orderApiResponse = $this->apiRequest($orderApiUrl, [
            'plan_id' => $planId,
            'period' => $period,
            'coupon_code' => $code
        ], $authorization);

        // 检查保存订单请求是否成功
        if (!$orderApiResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => '保存订单失败',
                'details' => $orderApiResponse->json(),
            ], $orderApiResponse->status());
        }

        // 解析订单保存成功后的数据
        $orderData = $orderApiResponse->json()['data'];
        $tradeNo = $orderData ?? null;

        // 构建结算订单API的URL
        $checkoutApiUrl = $baseUrl . '/api/v1/user/order/checkout';

        // 调用结算订单API
        $checkoutApiResponse = $this->apiRequest($checkoutApiUrl, ['trade_no' => $tradeNo], $authorization);

        // 检查结算订单请求是否成功
        if (!$checkoutApiResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => '结算订单失败',
                'details' => $checkoutApiResponse->json(),
            ], $checkoutApiResponse->status());
        }

        // 返回成功响应
        return response()->json([
            'status' => 'success',
            'couponCheck' => $couponApiResponse->json(),
            'orderSave' => $orderApiResponse->json(),
            'orderCheckout' => $checkoutApiResponse->json(),
        ]);
    }


    /**
     * 统一的API请求处理
     *
     * @param string $url
     * @param array $data
     * @param string $authData
     * @return \Illuminate\Http\Client\Response
     */
    protected function apiRequest($url, array $data, $authData)
    {
        try {
            $response = Http::withHeaders(['Authorization' => $authData])->post($url, $data);

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'API 请求异常',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 验证注册限制
     */
    private function validateRegisterLimits(Request $request)
    {
        // IP限制检查
        if ((int)config('v2board.register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            if ((int)$registerCountByIP >= (int)config('v2board.register_limit_count', 3)) {
                abort(500, __('Register frequently, please try again after :minute minute', [
                    'minute' => config('v2board.register_limit_expire', 60)
                ]));
            }
        }

        // reCAPTCHA验证
        if ((int)config('v2board.recaptcha_enable', 0)) {
            $recaptcha = new ReCaptcha(config('v2board.recaptcha_key'));
            $recaptchaResp = $recaptcha->verify($request->input('recaptcha_data'));
            if (!$recaptchaResp->isSuccess()) {
                abort(500, __('Invalid code is incorrect'));
            }
        }

        // 邮箱白名单验证
        $this->validateEmailRules($request);

        // 只有传了邀请码时才校验
        if ($request->has('invite_code')) {
            $this->validateInviteCode($request);
        }
    }

    /**
     * 验证邮箱规则
     */
    private function validateEmailRules(Request $request)
    {
        if ((int)config('v2board.email_whitelist_enable', 0)) {
            if (!Helper::emailSuffixVerify(
                $request->input('email'),
                config('v2board.email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT))
            ) {
                abort(500, __('Email suffix is not in the Whitelist'));
            }
        }

        if ((int)config('v2board.email_gmail_limit_enable', 0)) {
            $prefix = explode('@', $request->input('email'))[0];
            if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
                abort(500, __('Gmail alias is not supported'));
            }
        }
    }

    /**
     * 验证邀请码
     */
    private function validateInviteCode(Request $request)
    {
        if ((int)config('v2board.stop_register', 0)) {
            abort(500, __('Registration has closed'));
        }

        if ((int)config('v2board.invite_force', 0)) {
            if (empty($request->input('invite_code'))) {
                abort(500, __('You must use the invitation code to register'));
            }
        }
    }

    /**
     * 创建新用户
     */
    private function createNewUser(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        // 检查邮箱是否存在
        if (User::where('email', $email)->first()) {
            abort(500, __('Email already exists'));
        }

        $user = new User();
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 0;  // 显式设置默认值
        // 处理邀请码
        $this->handleInviteCode($request, $user);

        // 处理试用计划
        //$this->handleTryOutPlan($user);


        if (!$user->save()) {
            abort(500, __('Register failed'));
        }

        return $user;
    }

    /**
     * 处理邀请码逻辑
     */
    private function handleInviteCode(Request $request, User $user)
    {
        if ($request->input('invite_code')) {
            $inviteCode = InviteCode::where('code', $request->input('invite_code'))
                ->where('status', 0)
                ->first();
            if (!$inviteCode) {
                if ((int)config('v2board.invite_force', 0)) {
                    abort(500, __('Invalid invitation code'));
                }
            } else {
                $user->invite_user_id = $inviteCode->user_id ? $inviteCode->user_id : null;
                if (!(int)config('v2board.invite_never_expire', 0)) {
                    $inviteCode->status = 1;
                    $inviteCode->save();
                }
            }
        }
    }

    /**
     * 处理试用计划
     */
    private function handleTryOutPlan(User $user)
    {
        if ((int)config('v2board.try_out_plan_id', 0)) {
            $plan = Plan::find(config('v2board.try_out_plan_id'));
            if ($plan) {
                $user->transfer_enable = $plan->transfer_enable * 1073741824;
                $user->plan_id = $plan->id;
                $user->group_id = $plan->group_id;
                $user->expired_at = time() + (config('v2board.try_out_hour', 1) * 3600);
                $user->speed_limit = $plan->speed_limit;
            }
        }
    }

    /**
     * 处理邀请赠送
     */
    private function handleInvitePresent(User $user)
    {
        if ((int)config('v2board.invite_force_present') != 1) {
            return;
        }

        $plan = Plan::find((int)config('v2board.complimentary_packages'));
        if (!$plan) {
            return;
        }

        $inviter = User::find($user->invite_user_id);
        if (!$inviter || (int)config('v2board.try_out_plan_id') == $inviter->plan_id) {
            return;
        }

        DB::transaction(function () use ($user, $plan, $inviter) {

            if ($inviter->has_received_inviter_reward) {
                \Log::info('邀请人已获得过该用户的奖励', [
                    'inviter_id' => $inviter->id,
                    'user_id' => $user->id
                ]);
                return;
            }
            // 创建赠送订单
            $rewardOrder = new Order();
            $orderService = new OrderService($rewardOrder);
            $rewardOrder->user_id = $inviter->id;
            $rewardOrder->plan_id = $plan->id;

            // 从配置中读取赠送时长（小时）
            $giftHours = (int)config('v2board.complimentary_package_duration', 720); // 默认30天

            // 根据时长确定period
            if ($giftHours <= 24 * 30) {
                $rewardOrder->period = 'month_price';
                $periodLabel = '月付';
            } else if ($giftHours <= 24 * 90) {
                $rewardOrder->period = 'quarter_price';
                $periodLabel = '季付';
            } else if ($giftHours <= 24 * 180) {
                $rewardOrder->period = 'half_year_price';
                $periodLabel = '半年付';
            } else {
                $rewardOrder->period = 'year_price';
                $periodLabel = '年付';
            }

            // 计算赠送天数并设置
            $giftDays = round($giftHours / 24, 2);
            $rewardOrder->gift_days = $giftDays;

            // ...其他设置不变
            $rewardOrder->trade_no = Helper::guid();
            $rewardOrder->total_amount = 0;
            $rewardOrder->status = 3;
            $rewardOrder->type = 6; // 首单奖励类型
            $rewardOrder->invited_user_id = $user->id;
            $orderService->setInvite($user);
            $rewardOrder->save();

            // 更新邀请人有效期 - 保持不变
            $this->updateInviterExpiry($inviter, $plan, $rewardOrder);
        });
    }

    /**
     * 更新邀请人的有效期 - 支持套餐叠加和套餐抵扣
     */
    public function updateInviterExpiry(User $inviter, Plan $newPlan, Order $order)
    {
        try {
            $currentPlan = Plan::find($inviter->plan_id);
            if (!$currentPlan || $currentPlan->month_price <= 0 || $newPlan->month_price <= 0) {
                \Log::warning('套餐价格异常，跳过有效期计算');
                return; // 避免出现除零错误
            }

            $currentTimestamp = time();
            $hoursInMonth = 720; // 30天 * 24小时
            
            // 获取配置的赠送小时数
            $configComplimentaryHours = (int)config('v2board.complimentary_package_duration', 720);
            
            // 计算剩余有效期（小时）
            $remainingHours = max(0, floor(($inviter->expired_at - $currentTimestamp) / 3600));
            
            // 相同套餐 - 直接叠加赠送时间
            if ($currentPlan->id === $newPlan->id) {
                // 计算基础时间戳(如果过期就用当前时间)
                $baseTimestamp = $inviter->expired_at > $currentTimestamp ? $inviter->expired_at : $currentTimestamp;
                
                // 直接加上配置的赠送时间
                $expiredTime = $baseTimestamp + ($configComplimentaryHours * 3600);
                
                // 计算赠送天数（用于显示）
                $giftDays = round($configComplimentaryHours / 24, 2);
                
                // 更新邀请人到期时间
                $inviter->expired_at = $expiredTime;
                
                \Log::info('相同套餐叠加', [
                    'inviter_id' => $inviter->id,
                    'plan_id' => $currentPlan->id,
                    'old_expire_date' => date('Y-m-d H:i:s', $baseTimestamp),
                    'new_expire_date' => date('Y-m-d H:i:s', $expiredTime),
                    'gift_days' => $giftDays,
                    'gift_hours' => $configComplimentaryHours
                ]);
            }
            // 不同套餐
            else {
                // 检查是否启用套餐抵扣功能
                $enablePlanDeduction = (int)config('v2board.plan_change_enable', 1) === 1;
                
                if ($enablePlanDeduction && $remainingHours > 0) {
                    // 计算当前套餐剩余价值
                    $currentHourlyPrice = ($currentPlan->month_price / 100) / $hoursInMonth;
                    $remainingValue = $remainingHours * $currentHourlyPrice;
                    
                    // 计算新套餐每小时价值
                    $newHourlyPrice = ($newPlan->month_price / 100) / $hoursInMonth;
                    
                    // 使用剩余价值抵扣新套餐时长
                    $deductionHours = floor($remainingValue / $newHourlyPrice);
                    
                    // 总时长 = 抵扣时长 + 配置的赠送时长
                    $totalHours = $deductionHours + $configComplimentaryHours;
                    
                    \Log::info('不同套餐抵扣', [
                        'inviter_id' => $inviter->id,
                        'old_plan' => $currentPlan->id,
                        'new_plan' => $newPlan->id,
                        'remaining_hours' => $remainingHours,
                        'deduction_hours' => $deductionHours,
                        'gift_hours' => $configComplimentaryHours,
                        'total_hours' => $totalHours
                    ]);
                } else {
                    // 不启用抵扣或无剩余时间，直接使用赠送时长
                    $totalHours = $configComplimentaryHours;
                    
                    \Log::info('不同套餐替换', [
                        'inviter_id' => $inviter->id,
                        'old_plan' => $currentPlan->id,
                        'new_plan' => $newPlan->id,
                        'gift_hours' => $configComplimentaryHours
                    ]);
                }
                
                // 计算最终时长和到期时间
                $giftDays = round($configComplimentaryHours / 24, 2); // 只显示实际赠送天数
                $inviter->expired_at = $currentTimestamp + ($totalHours * 3600);
                
                // 更新套餐信息
                $inviter->plan_id = $newPlan->id;
                $inviter->group_id = $newPlan->group_id;
                $inviter->transfer_enable = $newPlan->transfer_enable * 1073741824;
            }

            // 保存邀请人信息更新
            if (!$inviter->save()) {
                throw new \Exception("无法保存邀请人信息");
            }

            // 更新订单的赠送天数字段(显示实际配置的赠送天数)
            $order->gift_days = $giftDays;
            $order->save();
            
            \Log::info('邀请赠送更新完成', [
                'inviter_id' => $inviter->id,
                'new_expired_at' => date('Y-m-d H:i:s', $inviter->expired_at),
                'gift_days' => $giftDays
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('邀请赠送更新失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'inviter_id' => $inviter->id,
                'order_id' => $order->id
            ]);
            return false;
        }
    }

    /**
     * 统一注册入口
     */
    public function unificationReg(Request $request)
    {
        $redeemInfo = null; // 先初始化，避免未定义

        // 1. 判断注册模式 - 兑换码注册优先级高于推广码注册
        if ($request->filled('redeem_code')) {
            // 兑换码注册流程
            $redeemInfo = $this->validateRedeemCode($request->input('redeem_code'));
            if (!$redeemInfo) {
                abort(400, '您的兑换码有误');
            }
            // 只有当兑换码指定了邀请关系要求，且提供了关联用户时才设置邀请人ID
            if (isset($redeemInfo['is_invitation']) && 
                $redeemInfo['is_invitation'] == 1 && 
                !empty($redeemInfo['user_id'])) {
                $request->merge(['invite_user_id' => $redeemInfo['user_id']]);
            }
        } elseif ($request->filled('invite_code')) {
            // 推广码注册流程
            $this->validateInviteCode($request);
        }

        // 2. 验证各项限制
        $this->validateRegisterLimits($request);

        // 3. 验证邮箱验证码
        if ((int)config('v2board.email_verify', 0)) {
            if (empty($request->input('email_code'))) {
                abort(500, __('Email verification code cannot be empty'));
            }
            if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
                abort(500, __('Incorrect email verification code'));
            }
        }

        // 4. 创建新用户
        $user = $this->createNewUser($request);

        // 5. 清理验证码缓存
        if ((int)config('v2board.email_verify', 0)) {
            Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        }

        // 6. 更新最后登录时间
        $user->last_login_at = time();
        $user->save();

        // 7. 更新IP限制缓存
        if ((int)config('v2board.register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            Cache::put(
                CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip()),
                (int)$registerCountByIP + 1,
                (int)config('v2board.register_limit_expire', 60) * 60
            );
        }
        $authService = new AuthService($user);
        // 8. 根据不同注册模式处理套餐分配
        if ($request->filled('redeem_code')) { 
            $this->handleRedeemPlan($user, $redeemInfo);
            
            // 处理邀请奖励 - 修改这里
            $inviteGiveType = (int)config('v2board.is_Invitation_to_give', 0);
            if ($inviteGiveType === 1 || $inviteGiveType === 3) {
                // 调用正确的函数处理邀请赠送
                $this->handleInvitePresent($user);
                
                // 记录日志
                \Log::info('兑换码注册 - 处理邀请赠送', [
                    'user_id' => $user->id,
                    'invite_user_id' => $user->invite_user_id,
                    'invite_type' => $inviteGiveType
                ]);
            }
            
            return response()->json([
                'data' => $authService->generateAuthData($request)
            ]);
        } else {
            // 根据配置决定是否立即赠送
            $inviteGiveType = (int)config('v2board.is_Invitation_to_give', 0);
            if ($inviteGiveType === 1 || $inviteGiveType === 3) {
                // 模式1和模式3都在注册时赠送一次
                $this->handleInvitePresent($user);
            }
        }
        //处理试用计划
        $this->handleTryOutPlan($user);
        //$this->handleInvitePresent($user);  //取消赠送逻辑

        // 10. 处理订单记录
        /*
        $tryOutHours = (int)config('v2board.try_out_hour', 1);
        $plan = Plan::find(config('v2board.try_out_plan_id'));
        $giftDays = round($tryOutHours / 24, 2); // 保留两位小数
        $order = new Order();
        $order->user_id = $user->id;
        $order->plan_id = $plan->id;
        $order->period = 'try_out';  // 修改这里，固定使用 try_out
        $order->trade_no = Helper::guid();
        $order->total_amount = 0; // 赠送订单金额为0
        $order->status = 3; // 已完成状态
        $order->type = 4; // 赠送类型
        $order->gift_days = $giftDays; // 赠送天数
        $order->redeem_code = ''; // 添加空字符串作为默认值
        $order->save();
        */
        // 11. 处理佣金 - 注册佣金
        /*
        if ($user->invite_user_id && (int)config('v2board.commission_status', 0) == 1) {
            $this->processCommissionForRegistration($user);
        }
        */
        return response()->json([
            'data' => $authService->generateAuthData($request)
        ]);
    }

    /**
     * 验证兑换码
     * @param string $redeemCode
     * @return array|null
     */
    public function validateRedeemCode($redeemCode)
    {
        if (empty($redeemCode)) {
            return null;
        }

        // 查找对应的转换记录
        $convert = \App\Models\Convert::where('redeem_code', $redeemCode)
            ->where('end_at', '>', time())
            ->first();
        if (!$convert) {
            return null;
        }

        // 检查兑换次数限制
        if ($convert->ordinal_number === -1) {
            abort(400, '该兑换码已无法使用');
        }

        // 移除强制邮箱检查，只在有邮箱时进行关联
        $userId = null;
        if (!empty($convert->email)) {
            $User = \App\Models\User::where('email', $convert->email)->first();
            if ($User) {
                $userId = $User->id;
            }
        }

        // 检查是否需要更新兑换次数
        if ($convert->ordinal_number > 0) {
            if ($convert->ordinal_number === 1) {
                $convert->ordinal_number = -1;  // 设置为已用尽状态
            } else {
                $convert->ordinal_number -= 1;  // 递减兑换次数
            }
            $convert->save();
        }
        
        return [
            'plan_id' => $convert->plan_id,
            'duration_unit' => $convert->duration_unit,
            'duration_value' => $convert->duration_value,
            'redeem_code' => $redeemCode,
            'user_id' => $userId,
            'is_invitation' => $convert->is_invitation ?? 0, // 添加是否需要邀请关系标志
            'email' => $convert->email ?? '' // 添加关联邮箱
        ];
    }

    /**
     * 处理兑换码套餐
     */
    public function handleRedeemPlan(User $user, array $redeemInfo)
    {
        // 1. 获取兑换码对应的套餐
        $newPlan = Plan::find($redeemInfo['plan_id']);
        if (!$newPlan) {
            return false;
        }

        // 2. 获取当前时间和用户当前套餐
        $currentTime = time();
        $currentPlan = null;
        $remainingSeconds = 0;
        $isSamePlan = false;
        
        if ($user->plan_id) {
            $currentPlan = Plan::find($user->plan_id);
            // 计算当前套餐剩余时间（秒）
            $remainingSeconds = max(0, $user->expired_at - $currentTime);
            $isSamePlan = $currentPlan && $currentPlan->id == $newPlan->id;
        }

        // 3. 计算新套餐的周期和到期时间
        $orderPeriod = '';
        $expiredTime = $currentTime;
        $days = 0;

        switch ($redeemInfo['duration_unit']) {
            case 'month':
                $expiredTime = strtotime("+{$redeemInfo['duration_value']} month", $currentTime);
                $orderPeriod = 'month_price';
                break;
            case 'quarter':
                $expiredTime = strtotime("+".($redeemInfo['duration_value'] * 3)." month", $currentTime);
                $orderPeriod = 'quarter_price';
                break;
            case 'half_year':
                $expiredTime = strtotime("+".($redeemInfo['duration_value'] * 6)." month", $currentTime);
                $orderPeriod = 'half_year_price';
                break;
            case 'year':
                $expiredTime = strtotime("+{$redeemInfo['duration_value']} year", $currentTime);
                $orderPeriod = 'year_price';
                break;
            default: // 按天计算
                $durationSeconds = $redeemInfo['duration_value'] * 86400;
                $expiredTime = $currentTime + $durationSeconds;
                $orderPeriod = 'month_price';
        }
        
        $durationSeconds = $expiredTime - $currentTime;
        $days = ceil($durationSeconds / 86400);
        
        // 4. 处理套餐逻辑
        if ($isSamePlan) {
            // 相同套餐 - 叠加时间
            $finalExpiredTime = $user->expired_at > $currentTime 
                ? $user->expired_at + $durationSeconds
                : $expiredTime;
            \Log::info('兑换码：相同套餐叠加时间', [
                'user_id' => $user->id,
                'plan_id' => $newPlan->id,
                'old_expired' => date('Y-m-d H:i:s', $user->expired_at),
                'new_expired' => date('Y-m-d H:i:s', $finalExpiredTime),
                'added_days' => $days
            ]);
        } else {
            // 不同套餐 - 检查是否启用套餐抵扣
            $enablePlanDeduction = (int)config('v2board.plan_change_enable', 1) === 1;
            
            if ($currentPlan && $remainingSeconds > 0 && $enablePlanDeduction) {
                // 计算当前套餐剩余价值
                $monthlyPrice = $currentPlan->month_price / 100; // 转为元
                if ($monthlyPrice > 0) {
                    $hoursInMonth = 720; // 30天 * 24小时
                    $hourlyPrice = $monthlyPrice / $hoursInMonth;
                    $remainingHours = $remainingSeconds / 3600;
                    $remainingValue = $remainingHours * $hourlyPrice;
                    
                    // 将剩余价值添加到余额
                    $user->balance = $user->balance + $remainingValue;
                    \Log::info('兑换码：不同套餐计算抵扣', [
                        'user_id' => $user->id,
                        'old_plan' => $currentPlan->id,
                        'new_plan' => $newPlan->id,
                        'remaining_value' => $remainingValue,
                        'new_balance' => $user->balance
                    ]);
                }
            } else {
                \Log::info('兑换码：直接覆盖套餐', [
                    'user_id' => $user->id,
                    'old_plan' => $user->plan_id,
                    'new_plan' => $newPlan->id
                ]);
            }
            
            $finalExpiredTime = $expiredTime;
        }

        // 5. 更新用户套餐信息
        $user->transfer_enable = $newPlan->transfer_enable * 1073741824;
        $user->plan_id = $newPlan->id;
        $user->group_id = $newPlan->group_id;
        $user->expired_at = $finalExpiredTime;
        $user->save();

        // 6. 创建订单记录
        try {
            $order = new Order();
            $orderService = new OrderService($order);
            $order->user_id = $user->id;
            $order->plan_id = $newPlan->id;
            $order->period = $orderPeriod;
            $order->trade_no = Helper::guid();
            $order->total_amount = 0;
            $order->status = 3;
            $order->type = 5; // 5兑换
            $order->redeem_code = $redeemInfo['redeem_code']; 
            $order->gift_days = round($days, 2);
            $order->invite_user_id = $redeemInfo['user_id'];
            
            if (!$order->save()) {
                \Log::error('兑换码订单创建失败', [
                    'user_id' => $user->id,
                    'plan_id' => $newPlan->id
                ]);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            \Log::error('兑换码订单异常', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查邮箱是否已存在
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEmail(Request $request)
    {
        try {
            // 验证请求参数
            $validated = $request->validate([
                'email' => 'required|email'
            ], [
                'email.required' => '邮箱地址不能为空',
                'email.email' => '邮箱格式不正确'
            ]);

            $email = $request->input('email');

            // 检查邮箱格式规则
            if ((int)config('v2board.email_whitelist_enable', 0)) {
                if (!Helper::emailSuffixVerify(
                    $email,
                    config('v2board.email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT))
                ) {
                    return response()->json([
                        'code' => 500,
                        'message' => '该邮箱后缀不在白名单内'
                    ]);
                }
            }

            // 检查 Gmail 别名限制
            if ((int)config('v2board.email_gmail_limit_enable', 0)) {
                $prefix = explode('@', $email)[0];
                if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
                    return response()->json([
                        'code' => 500,
                        'message' => '不支持 Gmail 别名'
                    ]);
                }
            }

            // 检查邮箱是否已存在
            $exists = User::where('email', $email)->exists();

            return response()->json([
                'code' => $exists ? 500 : 200,
                'message' => $exists ? '该邮箱已被注册' : '该邮箱可以使用',
                'data' => [
                    'email' => $email,
                    'exists' => $exists
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => $e->validator->errors()->first(),
            ]);
        } catch (\Exception $e) {
            \Log::error('检查邮箱异常:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => 500,
                'message' => '服务器错误:' . $e->getMessage()
            ]);
        }
    }

    /**
     * 处理首次付费邀请奖励
     */
    public function handleFirstOrderReward(Order $order)
    {
        // 1. 获取订单用户和其邀请人
        $user = User::find($order->user_id);
        if (!$user || !$user->invite_user_id) {
            \Log::info('用户不存在或无邀请人', [
                'order_id' => $order->id,
                'user_id' => $order->user_id
            ]);
            return;
        }

        // 2. 获取邀请人信息
        $inviter = User::find($user->invite_user_id);
        if (!$inviter) {
            \Log::info('邀请人不存在', [
                'user_id' => $user->id,
                'invite_user_id' => $user->invite_user_id
            ]);
            return;
        }

        // 3. 检查是否是首次付费订单
        $hasOtherPaidOrders = Order::where('user_id', $user->id)
            ->where('id', '!=', $order->id)
            ->where('status', 3) // 已支付
            ->where('total_amount', '>', 0) // 只检查付费订单
            ->exists();

        if ($hasOtherPaidOrders) {
            \Log::info('非首次付费订单，不触发邀请奖励', [
                'user_id' => $user->id,
                'order_id' => $order->id
            ]);
            return;
        }

        // 4. 检查被邀请用户是否已经触发过奖励
        if ($user->has_triggered_invite_reward) {
            \Log::info('该用户已经为邀请人带来过奖励', [
                'user_id' => $user->id,
                'inviter_id' => $inviter->id
            ]);
            return;
        }

        // 5. 处理邀请奖励
        $plan = Plan::find((int)config('v2board.complimentary_packages'));
        if (!$plan) {
            \Log::error('赠送套餐不存在');
            return;
        }

        try {
            // 创建赠送订单
            $rewardOrder = new Order();
            $orderService = new OrderService($rewardOrder);
            $rewardOrder->user_id = $inviter->id;
            $rewardOrder->plan_id = $plan->id;

            // 从配置中读取赠送时长（小时）
            $giftHours = (int)config('v2board.complimentary_package_duration', 720); // 默认30天

            // 根据时长确定period
            if ($giftHours <= 24 * 30) {
                $rewardOrder->period = 'month_price';
                $periodLabel = '月付';
            } else if ($giftHours <= 24 * 90) {
                $rewardOrder->period = 'quarter_price';
                $periodLabel = '季付';
            } else if ($giftHours <= 24 * 180) {
                $rewardOrder->period = 'half_year_price';
                $periodLabel = '半年付';
            } else {
                $rewardOrder->period = 'year_price';
                $periodLabel = '年付';
            }

            // 计算赠送天数并设置
            $giftDays = round($giftHours / 24, 2);
            $rewardOrder->gift_days = $giftDays;

            // ...其他设置不变
            $rewardOrder->trade_no = Helper::guid();
            $rewardOrder->total_amount = 0;
            $rewardOrder->status = 3;
            $rewardOrder->type = 6; // 首单奖励类型
            $rewardOrder->invited_user_id = $user->id;
            $orderService->setInvite($user);
            $rewardOrder->save();

            // 更新邀请人有效期 - 保持不变
            $this->updateInviterExpiry($inviter, $plan, $rewardOrder);

            \Log::info('首次付费邀请奖励发放成功', [
                'user_id' => $user->id,
                'inviter_id' => $inviter->id,
                'order_id' => $rewardOrder->id
            ]);

            // 处理订单佣金
            $this->processCommissionForOrder($order);
        } catch (\Exception $e) {
            \Log::error('首次付费邀请奖励发放失败', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'inviter_id' => $inviter->id
            ]);
            // 错误不中断执行，只记录日志
        }
    }

    /**
     * 处理注册佣金
     * @param User $user 新注册的用户
     */
    private function processCommissionForRegistration(User $user)
    {
        // 检查邀请关系和佣金功能是否开启
        if (!$user->invite_user_id || (int)config('v2board.commission_status', 0) !== 1) {
            return;
        }
        
        // 查找邀请人
        $inviter = User::find($user->invite_user_id);
        if (!$inviter) {
            return;
        }
        
        // 获取注册佣金金额
        $registerCommission = (float)config('v2board.invite_register_commission', 0);
        if ($registerCommission <= 0) {
            return;
        }

        // 创建佣金记录
        $commissionLog = new \App\Models\CommissionLog();
        $commissionLog->invite_user_id = $inviter->id; // 邀请人ID
        $commissionLog->user_id = $user->id; // 被邀请人ID
        $commissionLog->trade_no = Helper::guid();
        $commissionLog->order_amount = 0;
        $commissionLog->commission_amount = $registerCommission;
        $commissionLog->type = 0; // 0表示注册佣金
        $commissionLog->created_at = time();
        $commissionLog->updated_at = time();
        
        // 更新邀请人佣金余额
        if ($commissionLog->save()) {
            $inviter->commission_balance = $inviter->commission_balance + $registerCommission;
            $inviter->save();
            
            \Log::info('注册佣金发放成功', [
                'user_id' => $user->id,
                'inviter_id' => $inviter->id,
                'amount' => $registerCommission
            ]);
        }
    }

    /**
     * 处理订单佣金
     * @param Order $order 已支付的订单
     */
    public function processCommissionForOrder(Order $order)
    {
        // 检查订单状态和金额
        if ($order->status !== 3 || $order->total_amount <= 0) {
            return;
        }
        
        // 检查佣金系统是否启用
        if ((int)config('v2board.commission_status', 0) !== 1) {
            return;
        }
        
        // 获取用户信息
        $user = User::find($order->user_id);
        if (!$user || !$user->invite_user_id) {
            return;
        }
        
        // 查找邀请人
        $inviter = User::find($user->invite_user_id);
        if (!$inviter) {
            return;
        }
        
        // 检查是否已发放过该订单佣金
        $hasCommissionLog = \App\Models\CommissionLog::where('trade_no', $order->trade_no)
            ->where('type', 1)
            ->exists();
        if ($hasCommissionLog) {
            return;
        }
        
        // 计算佣金金额
        $commissionRate = (float)config('v2board.invite_commission', 10) / 100;
        $commissionAmount = $order->total_amount * $commissionRate;
        
        // 佣金金额过小则忽略
        if ($commissionAmount < 0.01) {
            return;
        }
        
        // 创建佣金记录
        $commissionLog = new \App\Models\CommissionLog();
        $commissionLog->invite_user_id = $inviter->id; // 邀请人ID
        $commissionLog->user_id = $user->id; // 被邀请人ID
        $commissionLog->trade_no = $order->trade_no;
        $commissionLog->order_amount = $order->total_amount;
        $commissionLog->commission_amount = $commissionAmount;
        $commissionLog->type = 1; // 1表示订单佣金
        $commissionLog->created_at = time();
        $commissionLog->updated_at = time();
        
        // 更新邀请人佣金余额
        if ($commissionLog->save()) {
            $inviter->commission_balance = $inviter->commission_balance + $commissionAmount;
            $inviter->save();
            
            \Log::info('订单佣金发放成功', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'inviter_id' => $inviter->id,
                'amount' => $commissionAmount
            ]);
        }
    }
}
