<?php

namespace App\Http\Controllers\Passport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passport\AuthRegister;
use App\Http\Requests\Passport\AuthForget;
use App\Http\Requests\Passport\AuthLogin;
use App\Jobs\SendEmailJob;
use App\Models\Order;
use App\Services\AuthService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Plan;
use App\Models\User;
use App\Models\InviteCode;
use App\Utils\Helper;
use App\Utils\Dict;
use App\Utils\CacheKey;
use ReCaptcha\ReCaptcha;

class AuthController extends Controller
{
    public function loginWithMailLink(Request $request)
    {
        if (!(int)config('v2board.login_with_mail_link_enable')) {
            abort(404);
        }
        $params = $request->validate([
            'email' => 'required|email:strict',
            'redirect' => 'nullable'
        ]);

        if (Cache::get(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $params['email']))) {
            abort(500, __('Sending frequently, please try again later'));
        }

        $user = User::where('email', $params['email'])->first();
        if (!$user) {
            return response([
                'data' => true
            ]);
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user->id, 300);
        Cache::put(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $params['email']), time(), 60);

        $redirect = '/#/login?verify=' . $code . '&redirect=' . ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
        if (config('v2board.app_url')) {
            $link = config('v2board.app_url') . $redirect;
        } else {
            $link = url($redirect);
        }

        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('Login to :name', [
                'name' => config('v2board.app_name', 'V2Board')
            ]),
            'template_name' => 'login',
            'template_value' => [
                'name' => config('v2board.app_name', 'V2Board'),
                'link' => $link,
                'url' => config('v2board.app_url')
            ]
        ]);

        return response([
            'data' => $link
        ]);
    }

    public function register(AuthRegister $request)
    {
        if ((int)config('v2board.register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            if ((int)$registerCountByIP >= (int)config('v2board.register_limit_count', 3)) {
                abort(500, __('Register frequently, please try again after :minute minute', [
                    'minute' => config('v2board.register_limit_expire', 60)
                ]));
            }
        }
        if ((int)config('v2board.recaptcha_enable', 0)) {
            $recaptcha = new ReCaptcha(config('v2board.recaptcha_key'));
            $recaptchaResp = $recaptcha->verify($request->input('recaptcha_data'));
            if (!$recaptchaResp->isSuccess()) {
                abort(500, __('Invalid code is incorrect'));
            }
        }
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
        if ((int)config('v2board.stop_register', 0)) {
            abort(500, __('Registration has closed'));
        }
        if ((int)config('v2board.invite_force', 0)) {
            if (empty($request->input('invite_code'))) {
                abort(500, __('You must use the invitation code to register'));
            }
        }
        if ((int)config('v2board.email_verify', 0)) {
            if (empty($request->input('email_code'))) {
                abort(500, __('Email verification code cannot be empty'));
            }
            if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
                abort(500, __('Incorrect email verification code'));
            }
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $exist = User::where('email', $email)->first();
        if ($exist) {
            abort(500, __('Email already exists'));
        }
        $user = new User();
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
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

        // try out
        if ((int)config('v2board.try_out_plan_id', 0)) {
            $plan = Plan::find(config('v2board.try_out_plan_id'));
            if ($plan) {
                $user->transfer_enable = $plan->transfer_enable * 1073741824;
                $user->plan_id = $plan->id;
                $user->group_id = $plan->group_id;
                $user->expired_at = time() + (config('v2board.try_out_hour', 1) * 3600);
                $user->speed_limit = $plan->speed_limit;
            }
        } else {
            // 如果未开启试用，设置默认值
            $user->transfer_enable = 0;
            $user->plan_id = 0;
            $user->group_id = 0;
            $user->expired_at = 0;
            $user->speed_limit = 0;
            $user->is_admin=0;
        }

        if (!$user->save()) {
            abort(500, __('Register failed'));
        }
        if ((int)config('v2board.email_verify', 0)) {
            Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        }

        $user->last_login_at = time();
        $user->save();

        if ((int)config('v2board.register_limit_by_ip_enable', 0)) {
            Cache::put(
                CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip()),
                (int)$registerCountByIP + 1,
                (int)config('v2board.register_limit_expire', 60) * 60
            );
        }

        $authService = new AuthService($user);
        
        //新增邀请判断 这里写赠送套餐逻辑 这里处理邀请着套餐赠送问题
        if ((int)config('v2board.invite_force_present')==1) {
            $plan = Plan::find((int)config('v2board.complimentary_packages'));

            if ($plan && $user->invite_user_id) {  // 添加邀请用户ID检查
                $user_data = User::where('id', $user->invite_user_id)->first();
                
                if ($user_data) {  // 添加用户存在性检查
                    // 检查试用计划ID是否存在且不等于当前用户计划ID
                    if (!$user_data->plan_id || (int)config('v2board.try_out_plan_id') != $user_data->plan_id) {
                        DB::beginTransaction();
                        try {
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
                            
                            if (!$order->save()) {
                                throw new \Exception('邀请赠送订单保存失败');
                            }
                            
                            $expired_at = $user_data->expired_at;    //当前上游客户的到期时间
                            $Plan1 = Plan::find($user_data->plan_id); //获取上游用户当前的套餐
                            $new_Plan = Plan::find((int)config('v2board.complimentary_packages'));//准备赠送套餐详细信息
                            if ($Plan1 && $new_Plan) {
                                // 确保当前套餐和赠送套餐的价格大于零
                                if ($Plan1->month_price > 0 && $new_Plan->month_price > 0) {

                                    // 假设一个月有720小时（30天 * 24小时）
                                    $hoursInMonth = 720;

                                    // 计算当前套餐的每小时价格（分转换为元）
                                    $currentHourlyPrice = ($Plan1->month_price / 100) / $hoursInMonth;

                                    // 计算赠送套餐的每小时价格（分转换为元）
                                    $complimentaryHourlyPrice = ($new_Plan->month_price / 100) / $hoursInMonth;

                                    // 计算赠送套餐在当前套餐价格下的等效小时数
                                    $equivalentComplimentaryHours = $complimentaryHourlyPrice / $currentHourlyPrice * $hoursInMonth;

                                    // 获取配置项中的额外赠送小时数
                                    $configComplimentaryHours = (int)config('v2board.complimentary_package_duration');

                                    // 计算总的赠送小时数
                                    $totalComplimentaryHours = $equivalentComplimentaryHours + $configComplimentaryHours;

                                    // 获取当前时间戳
                                    $currentTimestamp = time();

                                    // 计算当前套餐的剩余小时数
                                    $remainingHours = floor(($expired_at - $currentTimestamp) / 3600);

                                    // 计算总小时数
                                    $totalHours = $remainingHours + $totalComplimentaryHours;

                                    // 更新用户的到期时间
                                    $newExpirationTimestamp = $currentTimestamp + floor($totalHours * 3600);
                                    $user_data->expired_at = $newExpirationTimestamp;
                                    $user_data->save();
                                }
                            }

                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollback();
                            \Log::error('邀请赠送处理失败：' . $e->getMessage());
                        }
                    }
                } else {
                    \Log::warning('邀请用户不存在，用户ID：' . $user->invite_user_id);
                }
            }
        }
        return response()->json([
            'data' => $authService->generateAuthData($request)
        ]);
    }

    public function login(AuthLogin $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if ((int)config('v2board.password_limit_enable', 1)) {
            $passwordErrorCount = (int)Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
            if ($passwordErrorCount >= (int)config('v2board.password_limit_count', 5)) {
                abort(500, __('There are too many password errors, please try again after :minute minutes.', [
                    'minute' => config('v2board.password_limit_expire', 60)
                ]));
            }
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            abort(500, __('Incorrect email or password'));
        }
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $password,
            $user->password)
        ) {
            if ((int)config('v2board.password_limit_enable')) {
                Cache::put(
                    CacheKey::get('PASSWORD_ERROR_LIMIT', $email),
                    (int)$passwordErrorCount + 1,
                    60 * (int)config('v2board.password_limit_expire', 60)
                );
            }
            abort(500, __('Incorrect email or password'));
        }

        if ($user->banned) {
            abort(500, __('Your account has been suspended'));
        }

        $authService = new AuthService($user);
        return response([
            'data' => $authService->generateAuthData($request)
        ]);
    }

    public function token2Login(Request $request)
    {
        if ($request->input('token')) {
            $redirect = '/#/login?verify=' . $request->input('token') . '&redirect=' . ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
            if (config('v2board.app_url')) {
                $location = config('v2board.app_url') . $redirect;
            } else {
                $location = url($redirect);
            }
            return redirect()->to($location)->send();
        }

        if ($request->input('verify')) {
            $key =  CacheKey::get('TEMP_TOKEN', $request->input('verify'));
            $userId = Cache::get($key);
            if (!$userId) {
                abort(500, __('Token error'));
            }
            $user = User::find($userId);
            if (!$user) {
                abort(500, __('The user does not '));
            }
            if ($user->banned) {
                abort(500, __('Your account has been suspended'));
            }
            Cache::forget($key);
            $authService = new AuthService($user);
            return response([
                'data' => $authService->generateAuthData($request)
            ]);
        }
    }

    public function getQuickLoginUrl(Request $request)
    {
        $authorization = $request->input('auth_data') ?? $request->header('authorization');
        if (!$authorization) abort(403, '未登录或登陆已过期');

        $user = AuthService::decryptAuthData($authorization);
        if (!$user) abort(403, '未登录或登陆已过期');

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user['id'], 60);
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

    public function forget(AuthForget $request)
    {
        if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
            abort(500, __('Incorrect email verification code'));
        }
        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            abort(500, __('This email is not registered in the system'));
        }
        $user->password = password_hash($request->input('password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        $user->password_salt = NULL;
        if (!$user->save()) {
            abort(500, __('Reset failed'));
        }
        Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        return response([
            'data' => true
        ]);
    }
}
