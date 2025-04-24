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
            $order = new Order();
            $orderService = new OrderService($order);
            $order->user_id = $user->invite_user_id;
            $order->plan_id = $plan->id;
            $order->period = 'month_price';
            $order->trade_no = Helper::guid();
            $order->total_amount = 0;
            $order->status = 3;
            $order->type = 6;
            $orderService->setInvite($user);
            $order->save();
            // 更新邀请人信息
            $inviter->has_received_inviter_reward = 1; // 标记已获得邀请奖励

            // 计算并更新有效期
            $this->updateInviterExpiry($inviter,$plan,$order);
        });
    }

    /**
     * 更新邀请人的有效期
     */
    private function updateInviterExpiry(User $inviter, Plan $newPlan, Order $order)
    {

        try {
            $currentPlan = Plan::find($inviter->plan_id);
            if (!$currentPlan || $currentPlan->month_price <= 0 || $newPlan->month_price <= 0) {
                return;
            }

            // 计算每小时价格
            $hoursInMonth = 720; // 30天 * 24小时
            $currentHourlyPrice = ($currentPlan->month_price / 100) / $hoursInMonth;
            $complimentaryHourlyPrice = ($newPlan->month_price / 100) / $hoursInMonth;

            // 计算赠送时长
            $equivalentComplimentaryHours = $complimentaryHourlyPrice / $currentHourlyPrice * $hoursInMonth;
            $configComplimentaryHours = (int)config('v2board.complimentary_package_duration');
            $totalComplimentaryHours = $equivalentComplimentaryHours + $configComplimentaryHours;

            // 计算总赠送天数（保留两位小数）
            $giftDays = round($totalComplimentaryHours / 24, 2);
            // 计算新的到期时间
            $currentTimestamp = time();
            $remainingHours = floor(($inviter->expired_at - $currentTimestamp) / 3600);
            if ($remainingHours < 0) {
                $remainingHours = 0; // 如果已过期，从当前时间开始计算
            }
            $totalHours = $remainingHours + $totalComplimentaryHours;

            // 更新邀请人到期时间
            $inviter->expired_at = $currentTimestamp + floor($totalHours * 3600);
            $inviter->save();

            // 更新订单的赠送天数字段
            $order->gift_days = $giftDays;
            $order->save();
            // 记录日志
            \Log::info('邀请赠送更新成功', [
                'inviter_id' => $inviter->id,
                'gift_days' => $giftDays,
                'old_expired_at' => date('Y-m-d H:i:s', $currentTimestamp + ($remainingHours * 3600)),
                'new_expired_at' => date('Y-m-d H:i:s', $inviter->expired_at)
            ]);

        } catch (\Exception $e) {
            \Log::error('邀请赠送更新失败', [
                'error' => $e->getMessage(),
                'inviter_id' => $inviter->id,
                'order_id' => $order->id
            ]);
            throw $e;
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
            // 设置邀请人ID
            $request->merge(['invite_user_id' => $redeemInfo['user_id']]);
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
        if ($request->filled('redeem_code')) { // 这里改成 filled
            $this->handleRedeemPlan($user, $redeemInfo);
            // 处理邀请奖励
            $inviteGiveType = (int)config('v2board.is_Invitation_to_give', 0);
            if ($inviteGiveType === 1 || $inviteGiveType === 3) {
                $AuthController =new AuthController();
                $AuthController->handleInviteCode($request,$user);
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

        $User=\App\Models\User::where('email', $convert->email)->first();
        if (!$User) {
            return null;
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
            'user_id' => $User->id
        ];
    }

    /**
     * 处理兑换码套餐
     */
    public function handleRedeemPlan(User $user, array $redeemInfo)
    {
        $plan = Plan::find($redeemInfo['plan_id']);
        if (!$plan) {
            return false;
        }

        // 设置用户套餐信息
        $user->transfer_enable = $plan->transfer_enable * 1073741824;
        $user->plan_id = $plan->id;
        $user->group_id = $plan->group_id;

        // 计算到期时间和订单周期
        $duration = 0;
        $orderPeriod = '';
        $days = 0; // 用于计算赠送天数
        switch ($redeemInfo['duration_unit']) {
            case 'month':
                $duration = $redeemInfo['duration_value'] * 30 * 86400;
                $orderPeriod = 'month_price';
                $days = $redeemInfo['duration_value'] * 30;
                break;
            case 'quarter':
                $duration = $redeemInfo['duration_value'] * 90 * 86400;
                $orderPeriod = 'quarter_price';
                $days = $redeemInfo['duration_value'] * 90;
                break;
            case 'half_year':
                $duration = $redeemInfo['duration_value'] * 180 * 86400;
                $orderPeriod = 'half_year_price';
                $days = $redeemInfo['duration_value'] * 180;
                break;
            case 'year':
                $duration = $redeemInfo['duration_value'] * 365 * 86400;
                $orderPeriod = 'year_price';
                $days = $redeemInfo['duration_value'] * 365;
                break;
            default:
                $duration = $redeemInfo['duration_value'] * 86400;
                $orderPeriod = 'month_price'; // 默认按月
                $days = $redeemInfo['duration_value'] * 30;
        }
        $user->expired_at = time() + $duration;
        $user->save();
        $giftDays = round($days, 2); // 保留两位小数
        // 创建赠送订单记录
        try {
            $order = new Order();
            $orderService = new OrderService($order);

            $order->user_id = $user->id;
            $order->plan_id = $plan->id;
            $order->period = $orderPeriod;
            $order->trade_no = Helper::guid();
            $order->total_amount = 0; // 赠送订单金额为0
            $order->status = 3; // 已完成状态
            $order->type = 4; // 赠送类型
            $order->redeem_code = $redeemInfo['redeem_code']; // 记录兑换码
            $order->gift_days = $giftDays; // 赠送天数
            $order->invite_user_id = $redeemInfo['user_id'];
            // 保存订单
            if (!$order->save()) {
                \Log::error('兑换码赠送订单创建失败:', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'redeem_code' => $redeemInfo['redeem_code']
                ]);
                return false;
            }else{
                return true;
            }
        } catch (\Exception $e) {
            \Log::error('兑换码赠送订单异常:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
    private function handleFirstOrderReward(Order $order)
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
            ->where('type', '!=', 4) // 非赠送订单
            ->exists();

        if ($hasOtherPaidOrders) {
            \Log::info('非首次付费订单，不触发邀请奖励', [
                'user_id' => $user->id,
                'order_id' => $order->id
            ]);
            return;
        }

        // 4. 检查邀请人是否已获得该用户的奖励
        if ($inviter->has_received_inviter_reward) {
            \Log::info('邀请人已获得过该用户的奖励', [
                'inviter_id' => $inviter->id,
                'user_id' => $user->id
            ]);
            return;
        }

        // 5. 处理邀请奖励
        DB::transaction(function () use ($user, $inviter, $order) {
            $plan = Plan::find((int)config('v2board.complimentary_packages'));
            if (!$plan) {
                \Log::error('赠送套餐不存在');
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
            $rewardOrder->type = 6; // 首单奖励类型
            $rewardOrder->invited_user_id = $user->id; // 记录来源用户
            $orderService->setInvite($user);
            $rewardOrder->save();

            // 标记邀请人已获得该用户的奖励
            $inviter->has_received_inviter_reward = 1;
            $inviter->save();

            // 更新邀请人有效期
            $this->updateInviterExpiry($inviter, $plan, $rewardOrder);

            \Log::info('首次付费邀请奖励发放成功', [
                'user_id' => $user->id,
                'inviter_id' => $inviter->id,
                'order_id' => $rewardOrder->id
            ]);
        });
    }
}
