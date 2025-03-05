<?php

namespace App\Http\Controllers\AppClient;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\OrderSave;
use App\Http\Requests\Passport\CommSendEmailVerify;
use App\Http\Requests\Passport\AuthRegister;
use App\Http\Requests\Passport\AuthForget;
use App\Http\Requests\Passport\AuthLogin;
use App\Http\Requests\User\UserTransfer;
use App\Http\Requests\User\TicketWithdraw;
use App\Http\Requests\User\TicketSave;

use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\UserService;
use App\Services\ServerService;
use App\Services\TicketService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Services\TelegramService;
use App\Services\PlanService;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\InviteCode;
use App\Models\CommissionLog;
use App\Models\StatUser;
use App\Models\Ticket;
use App\Models\TicketMessage;

use App\Utils\Helper;
use App\Utils\Dict;
use App\Utils\CacheKey;
use ReCaptcha\ReCaptcha;

use Omnipay\Omnipay;
use Stripe\Stripe;
use Stripe\Source;
use Library\BitpayX;
use Library\MGate;
use Library\Epay;

use App\Http\Requests\Admin\UserSendMail;
use App\Jobs\SendEmailJob;

use App\Models\Knowledge;
use App\Models\Notice;
use App\Models\Coupon;


class TomatoController extends Controller
{

  private $config =  [
        "dns" => [
            "rules" => [
                [
                    "outbound" => [
                        "any"
                    ],
                    "server" => "local"
                ],
                [
                    "disable_cache" => true,
                    "geosite" => [
                        "category-ads-all"
                    ],
                    "server" => "block"
                ],
                [
                    "clash_mode" => "global",
                    "server" => "remote"
                ],
                [
                    "clash_mode" => "direct",
                    "server" => "local"
                ],
                [
                    "geosite" => "cn",
                    "server" => "local"
                ]
            ],
            "servers" => [
                [
                    "address" => "https://1.1.1.1/dns-query",
                    "detour" => "RULE",
                    "tag" => "remote"
                ],
                [
                    "address" => "https://223.5.5.5/dns-query",
                    "detour" => "direct",
                    "tag" => "local"
                ],
                [
                    "address" => "rcode://success",
                    "tag" => "block"
                ]
            ],
            "strategy" => "prefer_ipv4"
        ],
        "experimental" => [
            "clash_api" => [
                "external_controller" => "127.0.0.1:9890",
                "secret" => ""
            ]
        ],
        "inbounds" => [],
        "outbounds" => [],
        "route" => [
          "geoip" => [
              "path" => "geoip.db"
          ],
          "geosite" => [
              "path" => "geosite.db"
          ],
          "rules" => []
        ]
    ];


   public function appalert(Request $request) {

        $lang = $request->input('lang');
        $model = Notice::orderBy('created_at', 'DESC')
            ->where('show', 1);
        $res = $model->forPage(1, 10)
            ->get();

        foreach ($res as $item) {

            if (empty($item['tags']) || empty($item['tags'][0])) {
                continue;
            }

            if($item["tags"][0] == "弹窗" || $item["tags"][0] == "alert") {
              return response()->json([
                    'status' => 1,
                    'title' => $item["title"],
                    'msg' => $item["content"],
                    'context' => $item["content"]
                ]);
            }
        }

        return response()->json([
            'status' => 0,
            'title' => '',
            'msg' => '',
            'context' => ''
        ]);

    }

   public function config()
   {

     $methods = config('v2board.commission_withdraw_method', Dict::WITHDRAW_METHOD_WHITELIST_DEFAULT);

     $withdrawArr = array();
     foreach ($methods as $value) {
         array_push($withdrawArr, array(
              "name" => $value,
              "type" => $value)
        );
     }


     return response([
         'data' => [
             'isEmailVerify' => (int)config('v2board.email_verify', 0) ? 1 : 0,
             'isInviteForce' => (int)config('v2board.invite_force', 0) ? 1 : 0,
             //'appName' => config('v2board.app_name'),
             //'appUrl' => config('v2board.app_url'),
             //'tggroup' => config('v2board.telegram_discuss_link'), //tg群組 tg group
             //'website' => config('v2board.app_url'), //官方網站 Official website
             //'chatType' => 'chatwoot',
             //'chatLink' => '',
             //'chatID' => 'ALiYU5kaAV3jJAxW7kVkipED',
             //'currency_symbol' => config('v2board.currency_symbol', '¥'),
             //'currency' => config('v2board.currency', 'CNY'),
             //'withdraw_methods' => $withdrawArr,
             //'inviteurl' => '/#/register?code='
         ]
     ]);
   }


   public function iapCallBack(Request $request) {

       $token = $request->input('UID');
       $period = $request->input('PurchaseType');
       $ordernumber = $request->input('PurchaseID');
       $user = User::where('token', $token)->first();
       $pid = 0;
       $cycle = "";

       if (!$user) {
           return response()->json([
               'status' => 0,
               'msg' => 'User information error'
           ]);
       }

       return response()->json([
           'status' => 0,
           'msg' => 'User information error'
       ]);

       if($period == "month") {
           $pid = 61;
           $cycle = "month_price";
       } else if($period == "quarter") {
           $pid = 62;
           $cycle = "quarter_price";
       } else if($period == "half_year") {
           $pid = 63;
           $cycle = "half_year_price";
       } else if($period == "year") {
           $pid = 64;
           $cycle = "year_price";
       }

       $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
       $userHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

       $key = "8L82KzQZewO6OgLG";
       $iv = "LwTR6tTQCuhGSKhE";
       $decrypted = openssl_decrypt($userHeader, 'aes-128-cbc', $key, false, $iv);
       $parts = explode(":", $decrypted);
       $decryptedID = $parts[0];

       //验证是否iOS端请求

       if (strpos($userAgent, "us.bluetile.Bluetilevpn") !== false) {

        } else {
            return response()->json([
                'status' => 0,
                'msg' => 'Warning: Invalid parameter'
            ]);
        }


       //验证ID是否篡改
       if($ordernumber == $decryptedID) {

       } else {

         return response()->json([
             'status' => 0,
             'msg' => "Warning: Invalid parameter"
         ]);

       }


       $current_time = date("Y-m-d_H-i-s");
       $new_filename = "iOS内购_". $user->email . $current_time . ".txt";
       $body = "用户token=".$token."|"."购买周期=".$period."|"."订单号=".$ordernumber."|验证信息=".$decrypted;
       $filename = $user->email."_".$current_time.".txt";
       $myfile = fopen($filename,"w") or die("Unable to open file!");
       fwrite($myfile, $body);
       fclose($myfile);

       if($ordernumber == "") {

           // $plan = Plan::find($pid);
           //
           // DB::beginTransaction();
           // $order = new Order();
           // $orderService = new OrderService($order);
           // $order->user_id = $user->id;
           // $order->plan_id = $plan->id;
           // $order->period = $cycle;
           // $order->trade_no = 'iOS'.Helper::generateOrderNo();
           // $order->total_amount = $plan[$cycle];
           // $order->status = 0;
           // $orderService->setVipDiscount($user);
           // $orderService->setOrderType($user);
           // $orderService->setInvite($user);
           //
           // if (!$order->save()) {
           //     DB::rollback();
           //     return response()->json([
           //         'status' => 0,
           //         'msg' => '创建订单失败'
           //     ]);
           // }
           //
           // DB::commit();
           //
           // if (!$orderService->paid($order->trade_no)) {
           //     return response()->json([
           //         'status' => 0,
           //         'msg' => '订单购买失败'
           //     ]);
           // }
           //
           // $data = [
           //   "status" => 1,
           //   "data" => "success",
           //   'no' => $order->trade_no
           // ];
           //
           // return response($data);

       } else {


         $trade_no = "iOS".$ordernumber;

         $order = Order::where('trade_no', $trade_no)
             ->where('user_id', $user->id)
             ->where('status', 3)
             ->first();

         if (!$order) {

             //Order does not exist or has been paid

             $plan = Plan::find($pid);

             DB::beginTransaction();
             $order = new Order();
             $orderService = new OrderService($order);
             $order->user_id = $user->id;
             $order->plan_id = $plan->id;
             $order->period = $cycle;
             $order->trade_no = $trade_no;
             $order->total_amount = $plan[$cycle];
             $order->status = 0;

             $orderService->setVipDiscount($user);
             $orderService->setOrderType($user);
             $orderService->setInvite($user);

             if (!$order->save()) {
                 DB::rollback();
                 return response()->json([
                     'status' => 0,
                     'msg' => '创建订单失败'
                 ]);
             }

             DB::commit();

             if (!$orderService->paid($order->trade_no)) {
                 return response()->json([
                     'status' => 0,
                     'msg' => '订单购买失败'
                 ]);
             }

             $data = [
               "status" => 1,
               "data" => "success",
               'no' => $order->trade_no
             ];

             return response($data);

         } else {

           $data = [
             "status" => 1,
             "data" => "Order processed",
             'no' => $trade_no
           ];

           return response($data);

         }

       }


   }

    public function getTrafficLog(Request $request)
    {
        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
        }

        //近一周
        $limit_time = strtotime(date('Y-m-d 00:00:00',time()-86400 * 8));

        $builder = StatUser::select([
            'u',
            'd',
            'record_at',
            'user_id',
            'server_rate'
        ])
        ->where('user_id', $user->id)
        ->where('record_at', '>=', $limit_time)
        ->orderBy('record_at', 'DESC')
        ->get()
        ->unique('record_at');

        $data = [];
        foreach ($builder as $item) {
            $formatted_date = date('d', $item->record_at);
            $data[$formatted_date] = $item;
        }

        return response([
            'data' => $data,
            'status' => 1
        ]);
    }


    public function couponCheck(Request $request)
    {
        if (empty($request->input('code'))) {
            return response()->json([
              'status' => 0,
              'msg' => '优惠券不能为空'
            ]);
        }

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
        }

        $couponService = new CouponService($request->input('code'));
        $couponService->setPlanId($request->input('plan_id'));
        $couponService->setUserId($user->id);
        $couponService->check();
        return response([
            'status' => 1,
            'msg' => '已使用'.$request->input('code').'优惠券',
            'data' => $couponService->getCoupon()
        ]);
    }


    public function checktrade(Request $request) {

        $tradeNo = $request->input('trade_no');
        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
        }

        $order = Order::where('trade_no', $tradeNo)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 0,
                'msg' => '订单不存在'
            ]);
        }

        return response([
            'status' => $order->status
        ]);
    }

    public function ordercancel(Request $request)
    {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
        }

        if (empty($request->input('trade_no'))) {
            return response()->json([
                'status' => 0,
                'msg' => '无效参数'
            ]);
        }

        $order = Order::where('trade_no', $request->input('trade_no'))
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 0,
                'msg' => '订单不存在'
            ]);
        }

        if ($order->status !== 0) {
            return response()->json([
                'status' => 0,
                'msg' => '只能取消待处理订单'
            ]);
        }

        $orderService = new OrderService($order);
        if (!$orderService->cancel()) {
            return response()->json([
                'status' => 0,
                'msg' => '订单取消失败'
            ]);
        }

        return response([
            'status' => 1,
            'msg' => '订单取消成功',
            'data' => true
        ]);
    }

    public function checkout(Request $request){

        $tradeNo = $request->input('trade_no');
        $method = $request->input('method');
        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
       }

        $order = Order::where('trade_no', $tradeNo)
            ->where('user_id', $user->id)
            ->where('status', 0)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 0,
                'msg' => '订单不存在或已支付'
            ]);
        }

        // free process
        if ($order->total_amount <= 0) {

            $orderService = new OrderService($order);

            if (!$orderService->paid($order->trade_no)) {
                return response([
                    'status' => 0,
                    'msg' => 'free process error'
                ]);
            };

            return response([
                'status' => -1,
                'msg' => '套餐购买成功',
                'data' => true
            ]);
        }

        $payment = Payment::find($method);

        if (!$payment || $payment->enable !== 1) {
            return response()->json([
                'status' => 0,
                'msg' => '付款方式不可用'
            ]);
        }

        $paymentService = new PaymentService($payment->payment, $payment->id);

        $order->handling_amount = NULL;
        $handling_amount = NULL;

        if ($payment->handling_fee_fixed || $payment->handling_fee_percent) {
            $order->handling_amount = round(($order->total_amount * ($payment->handling_fee_percent / 100)) + $payment->handling_fee_fixed);
            $handling_amount = round(($order->total_amount * ($payment->handling_fee_percent / 100)) + $payment->handling_fee_fixed);
        }

        $order->payment_id = $method;

        if (!$order->save()) {
            return response()->json([
                'status' => 0,
                'msg' => '请求失败，请稍后再试'
            ]);
        }

        $result = $paymentService->pay([
            'trade_no' => $tradeNo,
            'total_amount' => isset($order->handling_amount) ? ($order->total_amount + $order->handling_amount) : $order->total_amount,
            'user_id' => $order->user_id,
            'stripe_token' => $request->input('token')
        ]);

        return response([
            'status' => 1,
            'data' => $result['data']
        ]);
    }


    public function orderdetail(Request $request) {


        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
       }

        $order = Order::where('user_id', $user->id)
            ->where('trade_no', $request->input('trade_no'))
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 0,
                'msg' => '订单不存在或已支付'
            ]);
        }

        $order['plan'] = Plan::find($order->plan_id);
        $order['try_out_plan_id'] = (int)config('v2board.try_out_plan_id');

        if (!$order['plan']) {
            return response()->json([
                'status' => 0,
                'msg' => '订阅计划不存在'
            ]);
        }

        if ($order->surplus_order_ids) {
            $order['surplus_orders'] = Order::whereIn('id', $order->surplus_order_ids)->get();
        }

        return response([
            'status' => 1,
            'data' => $order
        ]);

    }


    public function ordersave(Request $request) {

        $plan_id = $request->input('plan_id');
        $period = $request->input('period');
        $token = $request->input('token');
        $coupon_code = $request->input('coupon_code');

        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
       }

        $userService = new UserService();


        $orderNotComplete = Order::whereIn('status', [0, 1])
            ->where('user_id', $user->id)
            ->first();

        if ($orderNotComplete) {

            $orderService = new OrderService($orderNotComplete);
            if (!$orderService->cancel()) {
                return response()->json([
                    'status' => -2,
                    'msg' => '订单取消失败'
                ]);
            }

            return response()->json([
                'status' => -1,
                'no' => $orderNotComplete->trade_no,
                'msg' => '订单取消成功,继续支付'
            ]);
        }


        $planService = new PlanService($plan_id);
        $plan = $planService->plan;

        if (!$plan) {
            return response()->json([
                'status' => 0,
                'msg' => '套餐不存在'
            ]);
        }

        if ($user->plan_id !== $plan->id && !$planService->haveCapacity() && $period !== 'reset_price') {
            return response()->json([
                'status' => 0,
                'msg' => '当前产品已售罄'
            ]);
        }

        if ($plan[$period] === NULL) {
            return response()->json([
                'status' => 0,
                'msg' => '无法购买此付款期，请选择其他付款期'
            ]);
        }

        if ($period === 'reset_price') {
            if (!$userService->isAvailable($user) || $plan->id !== $user->plan_id) {
                return response()->json([
                    'status' => 0,
                    'msg' => '订购已过期或无有效订购，无法购买数据重置套餐'
                ]);
            }
        }

        if ((!$plan->show && !$plan->renew) || (!$plan->show && $user->plan_id !== $plan->id)) {
            if ($period !== 'reset_price') {
                return response()->json([
                    'status' => 0,
                    'msg' => '此套餐已售罄，请选择其他套餐'
                ]);
            }
        }

        if (!$plan->renew && $user->plan_id == $plan->id && $period !== 'reset_price') {
            return response()->json([
                'status' => 0,
                'msg' => '此套餐无法续订，请更换其他套餐'
            ]);
        }

        DB::beginTransaction();
        $order = new Order();
        $orderService = new OrderService($order);
        $order->user_id = $user->id;
        $order->plan_id = $plan->id;
        $order->period = $period;
        $order->trade_no = Helper::generateOrderNo();
        $order->total_amount = $plan[$period];

        if ($coupon_code) {
            $couponService = new CouponService($coupon_code);
            if (!$couponService->use($order)) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'msg' => '优惠券失效'
                ]);
            }
            $order->coupon_id = $couponService->getId();
        }

        $orderService->setVipDiscount($user);
        $orderService->setOrderType($user);
        $orderService->setInvite($user);

        if ($user->balance && $order->total_amount > 0) {
            $remainingBalance = $user->balance - $order->total_amount;
            $userService = new UserService();
            if ($remainingBalance > 0) {
                if (!$userService->addBalance($order->user_id, - $order->total_amount)) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 0,
                        'msg' => '余额不足'
                    ]);
                }
                $order->balance_amount = $order->total_amount;
                $order->total_amount = 0;
            } else {
                if (!$userService->addBalance($order->user_id, - $user->balance)) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 0,
                        'msg' => '余额不足'
                    ]);
                }
                $order->balance_amount = $user->balance;
                $order->total_amount = $order->total_amount - $user->balance;
            }
        }

        if (!$order->save()) {
            DB::rollback();
            return response()->json([
                'status' => 0,
                'msg' => '订单创建失败'
            ]);
        }

        DB::commit();

        return response([
            'status' => 1,
            'msg' => '订单创建成功',
            'data' => $order->trade_no
        ]);


    }


    public function withdraw(TicketWithdraw $request)
    {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
       }

      if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $user->email)) !== (string)$request->input('email_code')) {
            return response()->json([
                'status' => 0,
                'msg' => '验证码不正确'
            ]);
        }

        if ((int)config('v2board.withdraw_close_enable', 0)) {
            return response()->json([
              'status' => 0,
              'msg' => '不支持提现'
            ]);
        }
        if (!in_array(
            $request->input('withdraw_method'),
            config(
                'v2board.commission_withdraw_method',
                Dict::WITHDRAW_METHOD_WHITELIST_DEFAULT
            )
        )) {
            return response()->json([
              'status' => 0,
              'msg' => '不支持此提现方式'
            ]);
        }

        $limit = config('v2board.commission_withdraw_limit', 100);
        if ($limit > ($user->commission_balance / 100)) {
            return response()->json([
              'status' => 0,
              'msg' => '最低提现金额为'.$limit
            ]);
        }

        DB::beginTransaction();
        $subject = __('[Commission Withdrawal Request] This ticket is opened by the system');
        $ticket = Ticket::create([
            'subject' => $subject,
            'level' => 2,
            'user_id' => $user->id
        ]);

        if (!$ticket) {
            DB::rollback();
            return response()->json([
              'status' => 0,
              'msg' => '提现提交失败'
            ]);
        }
        $message = sprintf("%s\r\n%s",
            __('Withdrawal method') . "：" . $request->input('withdraw_method'),
            __('Withdrawal account') . "：" . $request->input('withdraw_account')
        );
        $ticketMessage = TicketMessage::create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'message' => $message
        ]);
        if (!$ticketMessage) {
            DB::rollback();
            return response()->json([
              'status' => 0,
              'msg' => '提现提交失败'
            ]);
        }
        DB::commit();
        return response([
            'status' => 1,
            'msg' => "提现提交成功"
        ]);
    }


    public function withdrawStatus(Request $request)
    {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
       }

        $ticket = Ticket::where('user_id', $user->id)
                ->where('status', 0)
                ->first();

        if($ticket) {

            if (strpos($ticket['subject'], "提现申请") !== false) {
                return response()->json([
                  'status' => 1,
                  'msg' => '已提交'
                ]);
            } else {
                return response()->json([
                  'status' => 0,
                  'msg' => '未提交'
                ]);
            }

        } else {

            return response()->json([
              'status' => 0,
              'msg' => '未提交'
            ]);

        }

    }


    public function transfer(UserTransfer $request)
    {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
       }

        if ($request->input('transfer_amount') > $user->commission_balance) {
            return response()->json([
              'status' => 0,
              'msg' => '佣金余额不足'
            ]);
        }
        $user->commission_balance = $user->commission_balance - $request->input('transfer_amount');
        $user->balance = $user->balance + $request->input('transfer_amount');
        if (!$user->save()) {
            return response()->json([
              'status' => 0,
              'msg' => '佣金转换余额失败'
            ]);
        }
        return response([
            'status' => 1,
            'msg' => "佣金转换余额成功"
        ]);
    }


     public function appinvite(Request $request) {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();
        $diycode = $request->input('code');
        $codes = InviteCode::orderBy('created_at', 'DESC')->where('user_id', $user->id)
            ->where('status', 0)
            ->get();

        $commission_rate = config('v2board.invite_commission', 10);

        if ($user->commission_rate) {
            $commission_rate = $user->commission_rate;
        }

        $uncheck_commission_balance = (int)Order::where('status', 3)
            ->where('commission_status', 0)
            ->where('invite_user_id', $user->id)
            ->sum('commission_balance');

        if (config('v2board.commission_distribution_enable', 0)) {
            $uncheck_commission_balance = $uncheck_commission_balance * (config('v2board.commission_distribution_l1') / 100);
        }

        //37403
        //15791

        $invite_users = CommissionLog::where('invite_user_id', 15791)
            ->where('get_amount', '>', 0)
            ->select([
                'id',
                'trade_no',
                'order_amount',
                'get_amount',
                'created_at'
            ])
            ->orderBy('created_at', 'DESC')
            ->get();

        if(count($codes) == 0){

            $inviteCodeNew = Helper::randomChar(8);
            $inviteCode = new InviteCode();
            $inviteCode->user_id = $user->id;
            $inviteCode->code = $inviteCodeNew;
            $inviteCode->save();

            return response()->json([
                'status' => 1,
                'code' => $inviteCodeNew,
                'invite_users' => (int)User::where('invite_user_id', $user->id)->count(),
                'invite_get_amount' => (int)CommissionLog::where('invite_user_id', $user->id)->sum('get_amount'),
                'invite_uncheck_commission_balance' => $uncheck_commission_balance,
                'invite_commission_balance' => (int)$user->commission_balance,
                'invite_commission_rate' => (int)$commission_rate,
                'inviteusers' => $invite_users
            ]);

        } else {

            return response()->json([
                'status' => 1,
                'code' => $codes[0]->code,
                'invite_users' => (int)User::where('invite_user_id', $user->id)->count(),
                'invite_get_amount' => (int)CommissionLog::where('invite_user_id', $user->id)->sum('get_amount'),
                'invite_uncheck_commission_balance' => $uncheck_commission_balance,
                'invite_commission_balance' => (int)$user->commission_balance,
                'invite_commission_rate' => (int)$commission_rate,
                'inviteusers' => $invite_users
            ]);
        }


    }


    public function getPaymentMethod(Request $request) {

        $methods = Payment::select([
            'id',
            'name',
            'payment',
            'icon',
            'handling_fee_fixed',
            'handling_fee_percent'
        ])
            ->where('enable', 1)
            ->orderBy('sort', 'ASC')
            ->get();

        return response([
            'data' => $methods
        ]);

    }


    public function appfaq(Request $request) {

        $user = User::find($request->input('id'));


        //test
        if($request->input('language') == "en") {

          $id = $request->input('id');

          if($id == ""){

              $knowledges = [

                  "official" => array([
                      "id" => 1,
                      "title" => "企业官方网站",
                      "updated_at" => 1668938628
                  ]),
                  "nodes" => array([
                      "id" => 2,
                      "title" => "如何选择最优节点?",
                      "updated_at" => 1668938628
                  ]),
                  "routing" => array([
                      "id" => 3,
                      "title" => "关于路由疑问解答",
                      "updated_at" => 1668938628
                  ]),

              ];

              return response([
                    'data' => $knowledges,
                    'total' => count($knowledges)
              ]);

          }


          if($id == 1) {

              $knowledges = [
                  "id" => 1,
                  "language" => "en-US",
                  "title" => "",
                  "body" => "Visit https://www.bluetile.biz",
                  "sort" => 2,
                  "show" => 1,
                  "created_at" => 1642066608,
                  "updated_at" => 1668938636
              ];

          } else if($id == 2) {

            $knowledges = [
                "id" => 2,
                "language" => "en-US",
                "title" => "",
                "body" => "查看节点延迟或选择智能推荐节点",
                "sort" => 2,
                "show" => 1,
                "created_at" => 1642066608,
                "updated_at" => 1668938636
            ];

          } else if($id == 3) {

              $knowledges = [
                  "id" => 3,
                  "language" => "en-US",
                  "title" => "",
                  "body" => "全局模式： VPN 网络代理所有网站或应用程序
智能模式：代理规则列表中的网站。
* 如果有特殊需要，则无需启用全局模式",
                  "sort" => 2,
                  "show" => 1,
                  "created_at" => 1642066608,
                  "updated_at" => 1668938636
              ];

          } else {

              $knowledges = [

              ];

          }

          return response([
              'data' => $knowledges
          ]);


        }

        //test


        if ($request->input('id')) {

            $knowledge = Knowledge::where('id', $request->input('id'))
                ->where('show', 1)
                ->first()
                ->toArray();

            if (!$knowledge) {
               return response([
                    'data' => ""
                ]);
            };

            return response([
                'data' => $knowledge
            ]);
        }

        $knowledges = Knowledge::select(['id', 'category', 'title', 'updated_at'])
            ->where('language', $request->input('language'))
            ->where('show', 1)
            ->where('category', '=', '常见问题')
            ->orderBy('sort', 'ASC')
            ->get();
            //->groupBy('category');
        return response([
            'data' => $knowledges
        ]);

    }


    public function appshop(Request $request) {

        $counts = PlanService::countActiveUsers();
        $plans = Plan::where('show', 1)->orderBy('sort', 'ASC')->get();
        //$plans = Plan::whereIn('id', [24,25,26])->where('show', 1)->get();

        foreach ($plans as $k => $v) {
            if ($plans[$k]->capacity_limit === NULL) continue;
            if (!isset($counts[$plans[$k]->id])) continue;
            $plans[$k]->capacity_limit = $plans[$k]->capacity_limit - $counts[$plans[$k]->id]->count;
        }


        $newarr = array();

         foreach ($plans as $newitem) {

            if (empty($newitem['content'])) {
             continue;
            }

            $html = $newitem['content'];
            $pattern = '/<div[^>]*id="TagArray"[^>]*>(.*?)<\/div>/s';

            if (preg_match($pattern, $html, $matches)) {

                $tagArrayContent = $matches[1];

                $planTags = trim(trim($tagArrayContent), "[]");
                $planTag = explode(",", $planTags);

                $tag1 = $planTag[0];

                if (!isset($newarr[$tag1])) {
                    $newarr[$tag1] = [
                        "name" => $tag1,
                        "plans" => [],
                        "tags" => []
                    ];
                }

                $newarr[$tag1]["plans"][] = $newitem;
                $newarr[$tag1]["tags"][] = $planTag;


            } else {
                //echo "未找到 id 为 'TagArray' 的元素";
            }


        }

        return response([
            'status' => 1,
            'data' => $newarr
        ]);

    }

   public function getTempToken(Request $request)
   {
       $user = User::where('token', $request->input('token'))->first();
       if (!$user) {
         return response()->json([
              'status' => 0,
              'msg' => 'TOKEN不能为空'
          ]);
       }

       $code = Helper::guid();
       $key = CacheKey::get('TEMP_TOKEN', $code);
       Cache::put($key, $user->id, 60);
       return response([
            'status' => 1,
            'code' => $code,
            'data' => $code
        ]);
   }

    private function isEmailVerify()
    {
        return response([
            'data' => (int)config('v2board.email_verify', 0) ? 1 : 0
        ]);
    }

    public function appsendEmailVerify(Request $request)
    {

        $email = $request->input('username');

        if (Cache::get(CacheKey::get('LAST_SEND_EMAIL_VERIFY_TIMESTAMP', $email))) {
            return response()->json([
                'code' => 0,
                'message' => '验证码已发送，请过一会再请求'
            ]);
        }
        $code = rand(1000, 9999);
        $subject = 'Blue加速器邮箱验证码';

        SendEmailJob::dispatch([
            'email' => $email,
            'subject' => $subject,
            'template_name' => 'verify',
            'template_value' => [
                'name' => 'Blue加速器',
                'code' => $code,
                'url' => ''
            ]
        ]);

        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', $email), $code, 300);
        Cache::put(CacheKey::get('LAST_SEND_EMAIL_VERIFY_TIMESTAMP', $email), time(), 60);
        return response()->json([
            'code' => 1,
            'message' => "验证码发送成功"
        ]);
    }

    private function getEmailSuffix()
    {
        $suffix = config('v2board.email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT);
        if (!is_array($suffix)) {
            return preg_split('/,/', $suffix);
        }
        return $suffix;
    }

    public function appchangePassword(UserChangePassword $request)
    {

        $user = User::find($request->input('userId'));
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $request->input('old_password'),
            $user->password)
        ) {
            return response()->json([
                'status' => 0,
                'msg' => '旧密码有误'
            ]);
        }
        $user->password = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        if (!$user->save()) {
            return response()->json([
                'status' => 0,
                'msg' => '保存失败'
            ]);
        }
        $request->session()->flush();
        return response([
            'status' => 1,
            'data' => true
        ]);
    }

    public function alert(Request $request)
    {
      return response()->json([
          'code' => 0,
          'title' => '',
          'link' => "",
          'content' => ""
      ]);
    }

    public function appnotice(Request $request)
    {

        $lang = $request->input('lang');
        $token = $request->input('tomatoToken');

        return response([
          "content" => "--- 修改更新弹窗
--- 修复一些错误
--- 优化功能",
          "tags" => ["alert"],
          "title" => "Blue加速器 1.0.7版本更新",
          "link" => "https://apps.apple.com/us/app/blue%E5%8A%A0%E9%80%9F%E5%99%A8/id6738642488",
          'total' => 1
        ]);

        if($token == "") {




        } else {

          $current = $request->input('current') ? $request->input('current') : 1;
          $pageSize = 15;
          $model = Notice::orderBy('created_at', 'DESC')
              ->where('show', 1);
          $total = $model->count();
          $res = $model->forPage($current, $pageSize)
              ->get();
          return response([
              'data' => $res,
              'total' => $total
          ]);

        }

    }


    public function appknowledge(Request $request)
    {
        $user = User::find($request->input('id'));

        //test
        if($request->input('language') == "en") {

          $id = $request->input('id');

          if($id == ""){

              $knowledges = [

              "IOS" => array(
                  [
                      "id" => 1,
                      "category" => "IOS",
                      "title" => "如何安装iOS客户端|How to install IOS",
                      "updated_at" => 1668938628
                  ]
                  ),

              ];

              return response([
                    'data' => $knowledges,
                    'total' => count($knowledges)
              ]);

          }


          if($id == 1) {

              $knowledges = [
                  "id" => 1,
                  "language" => "zh-CN",
                  "category" => "如何安装IOS",
                  "title" => "手机版",
                  "body" => "可以在官网下载iOS；\n[下载地址](https://google.com)",
                  "sort" => 2,
                  "show" => 1,
                  "created_at" => 1642066608,
                  "updated_at" => 1668938636
              ];

          } else {

              $knowledges = [

              ];

          }

          return response([
              'data' => $knowledges
          ]);


        }


        if ($request->input('id')) {
            $knowledge = Knowledge::where('id', $request->input('id'))
                ->where('show', 1)
                ->first()
                ->toArray();
            if (!$knowledge) {
               return response([
                    'data' => ""
                ]);
            };

            $subscribeUrl = Helper::getSubscribeUrl("/api/v1/client/subscribe?token={$user['token']}");
            $knowledge['body'] = str_replace('{{siteName}}', config('v2board.app_name', 'V2Board'), $knowledge['body']);
            $knowledge['body'] = str_replace('{{subscribeUrl}}', $subscribeUrl, $knowledge['body']);
            $knowledge['body'] = str_replace('{{urlEncodeSubscribeUrl}}', urlencode($subscribeUrl), $knowledge['body']);
            $knowledge['body'] = str_replace(
                '{{safeBase64SubscribeUrl}}',
                str_replace(
                    array('+', '/', '='),
                    array('-', '_', ''),
                    base64_encode($subscribeUrl)
                ),
                $knowledge['body']
            );
            return response([
                'data' => $knowledge
            ]);
        }


        $knowledges = Knowledge::select(['id', 'category', 'title', 'updated_at'])
            ->where('language', $request->input('language'))
            ->where('show', 1)
            ->orderBy('sort', 'ASC')
            ->get()
            ->groupBy('category');
        return response([
            'data' => $knowledges
        ]);

    }


    public function appregister(Request $request)
    {

        // if ((int)config('v2board.register_limit_by_ip_enable', 0)) {
        //     $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
        //     if ((int)$registerCountByIP >= (int)config('v2board.register_limit_count', 3)) {
        //         return response()->json([
        //             'code' => 0,
        //             'message' => 'Register frequently, please try again after 1 hour'
        //         ]);
        //     }
        // }

        // if ((int)config('v2board.email_whitelist_enable', 0)) {
        //     if (!Helper::emailSuffixVerify(
        //         $request->input('email'),
        //         config('v2board.email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT))
        //     ) {
        //         return response()->json([
        //             'status' => 0,
        //             'msg' => 'Email suffix is not in the Whitelist'
        //         ]);
        //     }
        // }
        // if ((int)config('v2board.email_gmail_limit_enable', 0)) {
        //     $prefix = explode('@', $request->input('email'))[0];
        //     if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
        //         return response()->json([
        //             'status' => 0,
        //             'msg' => 'Gmail alias is not supported'
        //         ]);
        //     }
        // }
        // if ((int)config('v2board.stop_register', 0)) {
        //     return response()->json([
        //         'status' => 0,
        //         'msg' => 'Registration has closed'
        //     ]);
        // }
        // if ((int)config('v2board.invite_force', 0)) {
        //     if (empty($request->input('invite_code'))) {
        //         return response()->json([
        //             'status' => 0,
        //             'msg' => 'You must use the invitation code to register'
        //         ]);
        //     }
        // }

        if ((int)config('v2board.email_verify', 0)) {
            if (empty($request->input('verify'))) {
                return response()->json([
                    'code' => 0,
                    'message' => '邮件验证码不能为空'
                ]);
            }
            if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('username'))) !== (string)$request->input('verify')) {
                return response()->json([
                    'code' => 0,
                    'message' => '邮件验证码不正确'
                ]);
            }
        }

        $email = $request->input('username');
        $password = $request->input('password');
        $exist = User::where('email', $email)->first();
        if ($exist) {
            return response()->json([
                'code' => 0,
                'message' => '邮箱已存在'
            ]);
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
                    return response()->json([
                        'code' => 0,
                        'message' => '无效邀请码'
                    ]);
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
            }
        }

        if (!$user->save()) {
            return response()->json([
                'code' => 0,
                'message' => '注册失败'
            ]);
        }
        if ((int)config('v2board.email_verify', 0)) {
            Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        }

        $data = [
            'token' => $user->token,
            'auth_data' => base64_encode("{$user->email}:{$user->password}")
        ];

        $user->last_login_at = time();
        $user->save();

        if ((int)config('v2board.register_limit_by_ip_enable', 0)) {
            Cache::put(
                CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip()),
                (int)$registerCountByIP + 1,
                (int)config('v2board.register_limit_expire', 60) * 60
            );
        }

        return response()->json([
            'code' => 1,
            'message' => '注册成功'
        ]);
    }


    public function appforget(AuthForget $request)
    {
        if (Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== $request->input('email_code')) {
            return response()->json([
                'status' => 0,
                'msg' => '邮箱验证码有误'
            ]);
        }
        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => '该邮箱不存在系统中'
            ]);
        }
        $user->password = password_hash($request->input('password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        if (!$user->save()) {
            return response()->json([
                'status' => 0,
                'msg' => '重置失败'
            ]);
        }
        Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        return response()->json([
            'status' => 1,
            'msg' => '重置成功'
        ]);
    }

    function sizecount($filesize) {
        if($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . 'G';
        } elseif($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . 'M';
        } elseif($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . 'K';
        } else {
            $filesize = $filesize . 'B';
        }
        return $filesize;
    }

    function curl_get_https($url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $tmpInfo = curl_exec($curl);
        curl_close($curl);
        return $tmpInfo;
    }

    public function applogin(Request $request)
    {

        $email = $request->input('username');
        $password = $request->input('password');

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'code' => 0,
                'message' => 'Incorrect username or password'
            ]);
        }
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $password,
            $user->password)
        ) {
            return response()->json([
                'code' => 0,
                'message' => 'Incorrect username or password'
            ]);
        }

        if ($user->banned) {
            return response()->json([
                'code' => 0,
                'message' => 'This account has been deactivated'
            ]);
        }


        $subscribeUrl = config('v2board.subscribe_url') ?: config('v2board.app_url');
        //$conf = curl_get_https($subscribeUrl.'/api/v1/client/subscribe?token='.$user->token);
        $conf = "";

        $planName = "无订阅";
        $currentPlan = NULL;
        $currentPlans = NULL;
        $confCount = 0;

        date_default_timezone_set("PRC");

        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        $days = $resetDay;

        if($days < 1){
            $days = 0;
        }

        if($user->plan_id != 0){
            $plan = Plan::find($user->plan_id);

            $orders = Order::select([
                'period',
                'total_amount',
                'trade_no'
            ])
            ->where('user_id', $user->id)
            ->whereNotIn('status', [0,2])
            ->orderBy('updated_at', 'DESC')
            ->first();

            if($plan){
                $planName = $plan["name"];
                $currentPlan = $orders;
                $currentPlans = $plan;
            }
        }

        if($conf != ""){
            $keyword_arr = explode(PHP_EOL, trim(base64_decode($conf)));
            $confCount = count($keyword_arr);
        } else {
            $conf = "";
        }

        $percentage = 0;

        if($user->transfer_enable != 0){

            $_percentage = number_format(($user->transfer_enable-($user->u+$user->d))/$user->transfer_enable*100,2);

            if ($_percentage < 0) {
                $percentage = 0;
            } else {
                $percentage = $_percentage;
            }
        }


        $codes = InviteCode::orderBy('created_at', 'DESC')->where('user_id', $user->id)
            ->where('status', 0)
            ->get();
            //->first();

        $nowinviteCode = "";

        if(count($codes) == 0){
            $inviteCodeNew = Helper::randomChar(8);
            $inviteCode = new InviteCode();
            $inviteCode->user_id = $user->id;
            $inviteCode->code = $inviteCodeNew;
            $inviteCode->save();
            $nowinviteCode = $inviteCodeNew;
        } else {
            $nowinviteCode = $codes[0]->code;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $deviceOS = $this->detectDeviceOS($userAgent);

        $temparray = array();
        $newarr = array();
        $iostemparray = array();

        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $servers = array_filter($servers, function($server) {
                if (isset($server['tags']) && is_array($server['tags'])) {
                    return !in_array('WEB', $server['tags']);
                }
                return true;
            });
            array_push($temparray, array(
                  "name" => "AutoSelect",
                  "server" => "",
                  "server_port" => 80,
                  "flag" => "AUTO",
                  "tags" => "",
                  "type" => "urltest",
                  "index" => 0)
            );

            $index = 0;
            foreach ($servers as $item){
                $index++;
                if (empty($item['tags']) || empty($item['tags'][0])) {
                    continue;
                }
                array_push($temparray, array(
                      "name" => $item['name'],
                      "server" => $item['host'],
                      "server_port" => $item['port'],
                      "flag" => $item['tags'][0],
                      "tags" => $item['tags'],
                      "type" => $item['type'],
                      "index" => $index)
                );
            }


            //add
            $blueindex = 0;

            $newarr["Auto"] = [
              "name" => "AutoSelect",
              "server" => "",
              "server_port" => 80,
              "flag" => "AUTO",
              "tags" => "",
              "list" => [],
              "index" => $blueindex
            ];

            foreach ($servers as $newitem) {

                $blueindex++;

                if (empty($newitem['tags']) || empty($newitem['tags'][0]) || empty($newitem['tags'][1]) || empty($newitem['tags'][2]) || empty($newitem['tags'][3])) {
                  continue;
                }

                $tag1 = $newitem["tags"][1];
                $tag2 = $newitem["tags"][2];
                $tag3 = $newitem["tags"][3];

                //add os
                if($deviceOS == $tag3) {

                  if (!isset($newarr[$tag1])) {

                      array_push($iostemparray, array(
                            "location" => $newitem['name'],
                            "line" => "ss,".$newitem['host'].",".$newitem['port'].", encrypt-method=".$newitem['cipher'].", password=".$user->uuid,
                            "isID" => true,
                            "emoji" => $newitem['tags'][0],
                            "id" => $blueindex)
                      );

                      $newarr[$tag1] = [
                          "name" => $tag1,
                          "flag" => $newitem['tags'][0],
                          "server" => $newitem['host'],
                          "server_port" => $newitem['port'],
                          "list" => [],
                          "index" => $blueindex
                      ];
                  }

                  $newarr[$tag1]["list"][] = [
                      "name" => $newitem['name'],
                      "group" => $tag2,
                      "server" => $newitem['host'],
                      "server_port" => $newitem['port'],
                      "flag" => $newitem['tags'][0],
                      "index" => $blueindex
                  ];

                }

            }

        }

        $key = "8L82KzQZewO6OgLG";
        $iv = "LwTR6tTQCuhGSKhE";
        $nodes = "";
        $denode = json_encode($temparray);
        $encrypted = openssl_encrypt($denode, 'aes-128-cbc', $key, false, $iv);

        if($encrypted != null && $encrypted != ""){
            $nodes = $encrypted;
        } else {
            $nodes = "";
        }

        $nodeConf = "";

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if($userAgent == "windows.v2board.app 2.0") {
            $deconf = $this->appnode($user->token,"Windows");
        } else {
            $deconf = $this->appnode($user->token,$deviceOS);
        }

        $expired_date = $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '长期有效';
        $confencrypted = openssl_encrypt($deconf, 'aes-128-cbc', $key, false, $iv);

        if($confencrypted != null && $confencrypted != ""){
            $nodeConf = $confencrypted;
        } else {
            $nodeConf = "";
        }


        //add
        $bluenodes = "";
        $bluedenode = json_encode($newarr);
        $blueencrypted = openssl_encrypt($bluedenode, 'aes-128-cbc', $key, false, $iv);

        if($blueencrypted != null && $blueencrypted != ""){
            $bluenodes = $blueencrypted;
        } else {
            $bluenodes = "";
        }

        $expDays = "";
        if($expired_date == "长期有效") {
            $expDays = "长期有效";
        } else {
            $expDays = $this->getExpirationStatus($expired_date);
        }

        if($user->email == "test@bluetilevpn.com") {
          $planName = "默认计划";
        }

        $iosnode = json_encode($iostemparray);

        $data = [
            'code' => 1,
            'message' => '登录成功',
            //'id' => $user->id,
            'uuid' => $user->uuid,
            'username' => $user->email,
            //'planName' => $planName,
            //'currentPlan' => $currentPlan,
            //'currentPlans' => $currentPlans,
            //'balance' => $user->balance,
            //'code' => $nowinviteCode,
            //'days' => $days,
            'trafficday' => intval($expDays),
            //'t' => $this->sizecount($user->t),
            //'u' => $this->sizecount($user->u),
            //'d' => $this->sizecount($user->d),
            'trafficused' => $this->sizecount($user->u + $user->d),
            'traffic' => $this->sizecount($user->transfer_enable),
            'token' => $user->token,
            'profile' => base64_encode($iosnode),
            //'expired' => $expired_date,
            //'residue' => $this->sizecount($user->transfer_enable - ($user->u + $user->d)),
            //'conf' => $conf,
            //'tfPercentage' => $percentage,
            //'confCount' => $confCount,
            //'configs' => $nodeConf,
            //'configsNodes' => $nodes,
            //'chatLink' => '',
            //'web' => "https://px.bluetile.art",
            //'link' => config('v2board.telegram_discuss_link'),
            //'logo' => config('v2board.logo'),
            //'url' => 'https://px.bluetile.art',
            //'bluenode' => $bluenodes
        ];

        return response($data);
    }

    public function verifycode(Request $request) {

        if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
            return response()->json([
                'status' => 0,
                'msg' => 'Email verification code is incorrect'
            ]);
        } else {
          return response()->json([
              'status' => 1,
              'msg' => 'Success'
          ]);
        }

    }


    public function verifyuser(Request $request) {

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => 'User does not exist'
            ]);
        } else {
            return response()->json([
                'status' => 1,
                'msg' => '用户已注册'
            ]);
        }

    }


    public function appupdate(Request $request) {

        $system = $request->input('device');
        $version = $request->input('v');

        if($system == "ios"){

            $_nowVersion = "1.0.7";

            //test
            if($version == "1.0.7"){
              return response()->json([
                  'code' => 0,
                  'msg' => '已是最新版本'
              ]);
            }

            if($version != $_nowVersion){

                return response()->json([
                    'code' => 1,
                    'msg' => '发现新版本 '.$_nowVersion
                ]);

            } else {

                return response()->json([
                    'code' => 0,
                    'msg' => '已是最新版本'
                ]);

            }


        }

    }

    public function appDelete(Request $request){

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => '账号信息错误'
            ]);
        }

        if ($user->banned) {
            return response()->json([
                'status' => 0,
                'msg' => '账号已删除'
            ]);
        }

        $user->banned = true;

        if ($user->save()) {
            $data["status"] = 1;
            $data["msg"] = "账号删除成功";
        }else{
            $data["status"] = 0;
            $data["msg"] = "账号删除失败";
        }

        return response($data);

    }


    function getDaysDifference($targetTime) {
        $targetTimestamp = strtotime($targetTime);
        $currentTimestamp = time();
        $timeDifference = $targetTimestamp - $currentTimestamp;

        if ($timeDifference > 0) {
            if ($timeDifference < 24 * 60 * 60 && $timeDifference > 60) { // 小于24小时且大于1分钟
                return "即将到期";
            } elseif ($timeDifference > 24 * 60 * 60) { // 大于或等于1天
                $days = floor($timeDifference / (60 * 60 * 24));
                return $days;
            } else { // 小于1分钟的情况
                return "0";
            }
        } else {
            return "已到期";
        }
    }


    function getExpirationStatus($expirationDate) {
        // 将当前时间和传入时间转换为时间戳
        $currentTime = time();
        $expirationTime = strtotime($expirationDate);

        // 判断到期时间是否已过去
        if ($expirationTime < $currentTime) {
            return "已到期"; // 已到期
        }

        // 计算时间差
        $timeDifference = $expirationTime - $currentTime;

        // 判断是否小于24小时
        if ($timeDifference <= 24 * 60 * 60) {
            return "即将到期"; // 即将到期
        }

        // 计算天数差
        $daysDifference = ceil($timeDifference / (24 * 60 * 60));
        return $daysDifference; // 返回天数
    }

    public function appsync(Request $request)
    {

        $token = $request->input('usertoken');

        if($token == "84a6dbd53adcee7249ef170fd0368511") {

            $token = "7bda17fddc1395dfd24ef1b94f4c4e8c";

        }

        $user = User::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => 'User information error'
            ]);
        }

        if ($user->banned) {
            return response()->json([
                'status' => 0,
                'msg' => 'This account has been suspended'
            ]);
        }

        //<2.0.5
        $subscribeUrl = config('v2board.subscribe_url') ?: config('v2board.app_url');
        //$conf = curl_get_https($subscribeUrl.'/api/v1/client/subscribe?token='.$user->token);
        $conf = "";

        $planName = "无订阅";
        $currentPlan = NULL;
        $currentPlans = NULL;
        $confCount = 0;

        date_default_timezone_set("PRC");

        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        $days = $resetDay;

        if($days < 1){
            $days = 0;
        }

        if($user->plan_id != 0){
            $plan = Plan::find($user->plan_id);
            $orders = Order::select([
                'period',
                'total_amount',
                'trade_no'
            ])
            ->where('user_id', $user->id)
            ->whereNotIn('status', [0,2])
            ->orderBy('updated_at', 'DESC')
            ->first();

            if($plan){
                $planName = $plan["name"];
                $currentPlan = $orders;
                $currentPlans = $plan;
            }
        }

        if($conf != ""){
            $keyword_arr = explode(PHP_EOL, trim(base64_decode($conf)));
            $confCount = count($keyword_arr);

            $key = "8L82KzQZewO6OgLG";
            $iv = "LwTR6tTQCuhGSKhE";

            $encrypted = openssl_encrypt($conf, 'aes-128-cbc', $key, false, $iv);

            if($encrypted != null && $encrypted != ""){
                $conf = $encrypted;
            }

        } else {
            $conf = "";
        }

        $percentage = 0;

        if($user->transfer_enable != 0){

            $_percentage = number_format(($user->transfer_enable-($user->u+$user->d))/$user->transfer_enable*100,2);

            if ($_percentage < 0) {
                $percentage = 0;
            } else {
                $percentage = $_percentage;
            }
        }

        $codes = InviteCode::orderBy('created_at', 'DESC')->where('user_id', $user->id)
            ->where('status', 0)
            ->get();
            //->first();

        $nowinviteCode = "";

        if(count($codes) == 0){
            $inviteCodeNew = Helper::randomChar(8);
            $inviteCode = new InviteCode();
            $inviteCode->user_id = $user->id;
            $inviteCode->code = $inviteCodeNew;
            $inviteCode->save();
            $nowinviteCode = $inviteCodeNew;
        } else {
            $nowinviteCode = $codes[0]->code;
        }

        $userService = new UserService();

        $temparray=array();
        $newarr = array();
        $iostemparray = array();

        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $deviceOS = $this->detectDeviceOS($userAgent);

        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $servers = array_filter($servers, function($server) {
                if (isset($server['tags']) && is_array($server['tags'])) {
                    return !in_array('WEB', $server['tags']);
                }
                return true;
            });
            array_push($temparray, array(
                  "name" => "AutoSelect",
                  "server" => "",
                  "server_port" => 80,
                  "flag" => "AUTO",
                  "tags" => "",
                  "type" => "urltest",
                  "index" => 0)
            );

            $index = 0;

            foreach ($servers as $item){
                $index++;
                if (empty($item['tags']) || empty($item['tags'][0])) {
                    continue;
                }
                array_push($temparray, array(
                      "name" => $item['name'],
                      "server" => $item['host'],
                      "server_port" => $item['port'],
                      "flag" => $item['tags'][0],
                      "tags" => $item['tags'],
                      "type" => $item['type'],
                      "index" => $index)
                );
            }

            //add

            $blueindex = 0;

            $newarr["Auto"] = [
              "name" => "AutoSelect",
              "server" => "",
              "server_port" => 80,
              "flag" => "AUTO",
              "tags" => "",
              "list" => [],
              "index" => $blueindex
            ];

            foreach ($servers as $newitem) {

                $blueindex++;

                if (empty($newitem['tags']) || empty($newitem['tags'][0]) || empty($newitem['tags'][1]) || empty($newitem['tags'][2]) || empty($newitem['tags'][3])) {
                  continue;
                }

                $tag1 = $newitem["tags"][1];
                $tag2 = $newitem["tags"][2];
                $tag3 = $newitem["tags"][3];

                //add os
                if($deviceOS == $tag3) {
                  if (!isset($newarr[$tag1])) {

                      array_push($iostemparray, array(
                            "location" => $newitem['name'],
                            "line" => "ss,".$newitem['host'].",".$newitem['port'].", encrypt-method=".$newitem['cipher'].", password=".$user->uuid,
                            "isID" => true,
                            "emoji" => $newitem['tags'][0],
                            "id" => $blueindex)
                      );

                      $newarr[$tag1] = [
                          "name" => $tag1,
                          "flag" => $newitem['tags'][0],
                          "server" => $newitem['host'],
                          "server_port" => $newitem['port'],
                          "list" => [],
                          "index" => $blueindex
                      ];
                  }
                  $newarr[$tag1]["list"][] = [
                      "name" => $newitem['name'],
                      "group" => $tag2,
                      "server" => $newitem['host'],
                      "server_port" => $newitem['port'],
                      "flag" => $newitem['tags'][0],
                      "index" => $blueindex
                  ];
                }
                // if (!isset($newarr[$tag1])) {
                //     $newarr[$tag1] = [
                //         "name" => $tag1,
                //         "flag" => $newitem['tags'][0],
                //         "server" => $newitem['host'],
                //         "server_port" => $newitem['port'],
                //         "list" => [],
                //         "index" => $blueindex
                //     ];
                // }
                //
                // $newarr[$tag1]["list"][] = [
                //     "name" => $newitem['name'],
                //     "group" => $tag2,
                //     "server" => $newitem['host'],
                //     "server_port" => $newitem['port'],
                //     "flag" => $newitem['tags'][0],
                //     "index" => $blueindex
                // ];
            }

        }

        $key = "8L82KzQZewO6OgLG";
        $iv = "LwTR6tTQCuhGSKhE";
        $nodes = "";
        $denode = json_encode($temparray);
        $encrypted = openssl_encrypt($denode, 'aes-128-cbc', $key, false, $iv);

        if($encrypted != null && $encrypted != ""){
            $nodes = $encrypted;
        } else {
            $nodes = "";
        }

        $nodeConf = "";
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if($userAgent == "windows.v2board.app 2.0") {
            $deconf = $this->appnode($user->token,"Windows");
        } else {
            $deconf = $this->appnode($user->token,$deviceOS);
        }
        $confencrypted = openssl_encrypt($deconf, 'aes-128-cbc', $key, false, $iv);

        if($confencrypted != null && $confencrypted != ""){
            $nodeConf = $confencrypted;
        } else {
            $nodeConf = "";
        }

        $expired_date = $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '长期有效';

        //add
        $bluenodes = "";
        $bluedenode = json_encode($newarr);
        $blueencrypted = openssl_encrypt($bluedenode, 'aes-128-cbc', $key, false, $iv);

        if($blueencrypted != null && $blueencrypted != ""){
            $bluenodes = $blueencrypted;
        } else {
            $bluenodes = "";
        }

        $expDays = "";
        if($expired_date == "长期有效") {
            $expDays = "长期有效";
        } else {
            $expDays = $this->getExpirationStatus($expired_date);
        }

        if($user->email == "test@bluetilevpn.com") {
          $planName = "默认计划";
        }

        $iosnode = json_encode($iostemparray);

        $data = [
            'code' => 1,
            'message' => '登录成功',
            //'id' => $user->id,
            'uuid' => $user->uuid,
            'username' => $user->email,
            //'planName' => $planName,
            //'currentPlan' => $currentPlan,
            //'currentPlans' => $currentPlans,
            //'balance' => $user->balance,
            //'code' => $nowinviteCode,
            //'days' => $days,
            'trafficday' => intval($expDays),
            //'t' => $this->sizecount($user->t),
            //'u' => $this->sizecount($user->u),
            //'d' => $this->sizecount($user->d),
            'trafficused' => $this->sizecount($user->u + $user->d),
            'traffic' => $this->sizecount($user->transfer_enable),
            'token' => $user->token,
            'profile' => base64_encode($iosnode),
            //'expired' => $expired_date,
            //'residue' => $this->sizecount($user->transfer_enable - ($user->u + $user->d)),
            //'conf' => $conf,
            //'tfPercentage' => $percentage,
            //'confCount' => $confCount,
            //'configs' => $nodeConf,
            //'configsNodes' => $nodes,
            //'chatLink' => '',
            //'web' => "https://px.bluetile.art",
            //'link' => config('v2board.telegram_discuss_link'),
            //'logo' => config('v2board.logo'),
            //'url' => 'https://px.bluetile.art',
            //'bluenode' => $bluenodes
        ];

        return response($data);

    }


    function detectDeviceOS($userAgent) {
        if (stripos($userAgent, 'Android') !== false) {
            return "Android";
        }
        // 判断是否为iOS设备
        elseif (stripos($userAgent, 'iOS') !== false || stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false || stripos($userAgent, 'iPod') !== false) {
            return "iOS";
        }
        // 判断是否为Mac设备
        elseif (stripos($userAgent, 'Bluetile macOS') !== false) {
            return "Mac";
        }
        // 判断是否为Windows设备
        elseif (stripos($userAgent, 'Windows') !== false) {
            return "Windows";
        }
        // 如果无法判断设备
        else {
            return "iOS";
        }
    }

    public function appnode($token,$type) {

        if($token == "") {
            return "";
        }

        $user = User::where('token', $token)->first();

        if (!$user) {
            return "";
        }

        if ($user->banned) {
            return "";
        }

        $userService = new UserService();

        if ($userService->isAvailable($user)) {

            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $servers = array_filter($servers, function($server) {
                if (isset($server['tags']) && is_array($server['tags'])) {
                    return !in_array('WEB', $server['tags']);
                }
                return true;
            });
            $data = $this->config;
            $proxy = [];
            $proxies = [];
            $ruleProxies = [];
            $serversName = [];

            $uuidtest = $user->uuid;

            foreach ($servers as $item) {

              if (empty($item['tags']) || empty($item['tags'][0]) || empty($item['tags'][1]) || empty($item['tags'][2]) || empty($item['tags'][3])) {
                continue;
              }

              $tag3 = $item["tags"][3];

              if($tag3 == $type) {

                if ($item['type'] === 'shadowsocks'
                    && in_array($item['cipher'], [
                        'aes-128-gcm',
                        'aes-192-gcm',
                        'aes-256-gcm',
                        'chacha20-ietf-poly1305'
                    ])
                ) {
                    array_push($proxy, $this->buildShadowsocks($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }
                if ($item['type'] === 'vmess' || $item['type'] === 'v2ray') {
                    array_push($proxy, $this->buildVmess($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }
                if ($item['type'] === 'trojan') {
                    array_push($proxy, $this->buildTrojan($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }

                if ($item['type'] === 'vless') {
                    array_push($proxy, $this->buildVless($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }

                 if ($item['type'] === 'hysteria') {
                    array_push($proxy, $this->buildHysteria($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                 }

              }

            }

            $hostsData = [
                //'domain' => $serversName,
                'geosite' => "cn",
                'server' => "local"
            ];

            //Direct
            $directOutbound = [
                'tag' => "direct",
                'type' => "direct"
            ];

            //dns
            $dnsOutbound = [
                'tag' => "dns",
                'type' => "dns"
            ];

            //block
            $blockOutbound = [
                'tag' => "block",
                'type' => "block"
            ];

            $autoOutbound = [
                "type" => "urltest",
                "tag" => "AutoSelect",
                "outbounds" => $proxies,
                "url" => "https://www.gstatic.com/generate_204",
                "interval" => "1m"
            ];

            //auto
            array_push($proxies, "AutoSelect");
            array_push($ruleProxies, "AutoSelect");

            //RULE
            array_push($ruleProxies, "direct");
            $ruleOutbound = [
                'outbounds' => $ruleProxies,
                'tag' => "RULE",
                'type' => "selector"
            ];

            //GLOBAL
            $globalOutbound = [
                'outbounds' => $proxies,
                'tag' => "GLOBAL",
                'type' => "selector"
            ];

            //hosts
            //array_push($data["dns"]["rules"], $hostsData);

            //outbounds
            $outbounds = [];
            $outbounds += $proxy;

            //auto
            array_push($outbounds, $autoOutbound);
            array_push($outbounds, $globalOutbound);
            array_push($outbounds, $ruleOutbound);
            array_push($outbounds, $directOutbound);
            array_push($outbounds, $blockOutbound);
            array_push($outbounds, $dnsOutbound);

            $data["outbounds"] = $outbounds;

            //rules
            $adsRule = [
               "outbound" => "block",
               "geosite" => "category-ads-all"
            ];

            $dnsRule = [
               "outbound" => "dns",
               "protocol" => "dns"
            ];

            $globalRule = [
               "clash_mode" => "global",
               "outbound" => "GLOBAL"
            ];

            $directRule = [
               "geoip" => [
                    "cn",
                    "private"
               ],
               "outbound" => "direct"
            ];

            $directRulegeosite = [
               "geosite" => "cn",
               "outbound" => "direct"
            ];

            $rule = [
               "clash_mode" => "rule",
               "outbound" => "RULE"
            ];

            $tkRule = [
               "geosite" => [
                    "tiktok",
                    "openai"
                ],
               "domain_keyword" => [
                    "tiktokcdn",
                    "tiktok",
                    "chatgpt"
               ],
               "domain_suffix" => [
                    "tiktokcdn.com",
                    "byteoversea.com",
                    "tiktokv.com",
                    "ibytedtos.com"
               ],
               "outbound" => "RULE"
            ];

            array_push($data["route"]["rules"], $adsRule);
            array_push($data["route"]["rules"], $dnsRule);
            array_push($data["route"]["rules"], $tkRule);
            array_push($data["route"]["rules"], $globalRule);
            array_push($data["route"]["rules"], $directRule);
            array_push($data["route"]["rules"], $directRulegeosite);
            array_push($data["route"]["rules"], $rule);


            //inbounds
            $otherinbounds = [
                "auto_route" => true,
                "domain_strategy" =>  "prefer_ipv4",
                "endpoint_independent_nat" =>  true,
                "inet4_address" => "172.19.0.1/30",
                "inet6_address" => "2001:0470:f9da:fdfa::1/64",
                "mtu" => 9000,
                "sniff" => true,
                "sniff_override_destination" => true,
                "stack" => "system",
                "strict_route" => true,
                "type" => "tun"
            ];

            $wininbounds = [
              "listen" => "127.0.0.1",
              "listen_port" => 10090,
              "sniff" => true,
              "tag" => "mixed-in",
              "type" => "mixed"
            ];

            $inbounds = [];

            //add
            if($type == "Windows") {
              array_push($inbounds, $wininbounds);
              $data["inbounds"] = $inbounds;
            } else {
              array_push($inbounds, $otherinbounds);
              $data["inbounds"] = $inbounds;
              $data['route']['auto_detect_interface'] = true;
            }

            // if($type == "") {
            //     array_push($inbounds, $otherinbounds);
            //     $data["inbounds"] = $inbounds;
            //     $data['route']['auto_detect_interface'] = true;
            // } else {
            //     array_push($inbounds, $wininbounds);
            //     $data["inbounds"] = $inbounds;
            // }

            $jsonString = json_encode($data);

            return $jsonString;

        } else {

            return "";

        }

    }


    //xboard version
    public static function buildHysteria($password, $server) {
       $passwd = $password;
       $array = [
           'server' => $server['host'],
           'server_port' => $server['port'],
           'tls' => [
               'enabled' => true,
               'insecure' => $server['insecure'] ? true : false,
               'server_name' => $server['server_name'] ? $server['server_name'] : ""
               //'apln' => "h3"
           ]
       ];

       if (is_null($server['version']) || $server['version'] == 1) {

           $array['auth_str'] = $password;
           $array['tag'] = $server['name'];
           $array['type'] = 'hysteria';
           $array['up_mbps'] = $server['down_mbps'];
           $array['down_mbps'] = $server['up_mbps'];
           if (isset($server['obfs']) && isset($server['obfs_password'])) {
               $array['obfs'] = $server['obfs_password'];
           }
           $array['disable_mtu_discovery'] = true;

       } elseif ($server['version'] == 2) {

           $array['password'] = $password;
           $array['tag'] = $server['name'];
           $array['type'] = 'hysteria2';
           $array['password'] = $password;

           $array['up_mbps'] = $server['down_mbps'];
           $array['down_mbps'] = $server['up_mbps'];

           if (isset($server['is_obfs'])) {
               $array['obfs']['type'] = "salamander";
               $array['obfs']['password'] = $server['server_key'];
           }
       }

       return $array;
   }

     public static function buildShadowsocks($uuid, $server)
    {
        $array = [];
        $array['type'] = 'shadowsocks';
        $array['tag'] = $server['name'];
        $array['method'] = $server['cipher'];
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['password'] = $uuid;
        return $array;
    }

    public static function buildVmess($uuid, $server)
    {
        $array = [];
        $array['type'] = 'vmess';
        $array['tag'] = $server['name'];
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['uuid'] = $uuid;
        $array['security'] = 'auto';
        $array['alter_id'] = 0;
        $array['global_padding'] = false;
        $array['authenticated_length'] = false;

        if ($server['tls']) {
            $array['tls'] = [];
            $array['tls']['enabled'] = true;

            if ($server['tlsSettings']) {
                $tlsSettings = $server['tlsSettings'];
                if (isset($tlsSettings['allowInsecure']) && !empty($tlsSettings['allowInsecure']))
                    $array['tls']['insecure'] = ($tlsSettings['allowInsecure'] ? true : false);
                if (isset($tlsSettings['serverName']) && !empty($tlsSettings['serverName']))
                    $array['tls']['server_name'] = $tlsSettings['serverName'];
            }
        }


        if ($server['network'] === 'ws') {

            $array['transport'] = [];

            $array['transport']['type'] = "ws";

            if ($server['networkSettings']) {
                $wsSettings = $server['networkSettings'];
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    $array['transport']['path'] = $wsSettings['path'];
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    $array['transport']['headers'] = ['Host' => $wsSettings['headers']['Host']];
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    $array['transport']['path'] = $wsSettings['path'];
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    $array['transport']['headers'] = ['Host' => $wsSettings['headers']['Host']];
            }

            $array['transport']['early_data_header_name'] = "Sec-WebSocket-Protocol";
        }

        if ($server['network'] === 'grpc') {
            $array['transport']['type'] ='grpc';
            if ($server['networkSettings']) {
                $grpcSettings = $server['networkSettings'];
                if (isset($grpcSettings['serviceName'])) $array['transport']['service_name'] = $grpcSettings['serviceName'];
            }
        }

        return $array;
    }

    public static function buildVless($uuid, $server) {

        $array = [];
        $array['type'] = 'vless';
        $array['tag'] = $server['name'];
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['uuid'] = $uuid;
        $array['flow'] = $server["flow"];

        if ($server['tls']) {
            $array['tls'] = [];
            $array['tls']['enabled'] = true;
            $array['tls']['disable_sni'] = false;

            if ($server['tls_settings']) {

                $tlsSettings = $server['tls_settings'];

                //if (isset($tlsSettings['allow_insecure']) && !empty($tlsSettings['allow_insecure']))
                    $array['tls']['insecure'] = false; //($tlsSettings['allow_insecure'] ? true : false);

                if (isset($tlsSettings['server_name']) && !empty($tlsSettings['server_name']))
                    $array['tls']['server_name'] = $tlsSettings['server_name'];


                $array["tls"]['utls'] = [];
                $array["tls"]['utls']['enabled'] = true;
                $array["tls"]['utls']['fingerprint'] = "";

                $array["tls"]['reality'] = [];
                $array["tls"]['reality']['enabled'] = true;
                $array["tls"]['reality']['public_key'] = $server['tls_settings']["public_key"];
                $array["tls"]['reality']['short_id'] = $server['tls_settings']["short_id"];
            }

            $array['packet_encoding'] = "xudp";
        }

        return $array;

    }

    protected function buildTrojan($password, $server)
    {
        $array = [];
        $array['tag'] = $server['name'];
        $array['type'] = 'trojan';
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['password'] = $password;

        $array['tls'] = [
            'enabled' => true,
            'insecure' => $server['allow_insecure'] ? true : false,
            'server_name' => $server['server_name']
        ];

        if(isset($server['network']) && in_array($server['network'], ["grpc", "ws"])){
            $array['transport']['type'] = $server['network'];
            // grpc配置
            if($server['network'] === "grpc" && isset($server['network_settings']['serviceName'])) {
                $array['transport']['service_name'] = $server['network_settings']['serviceName'];
            }
            // ws配置
            if($server['network'] === "ws") {
                if(isset($server['network_settings']['path'])) {
                    $array['transport']['path'] = $server['network_settings']['path'];
                }
                if(isset($server['network_settings']['headers']['Host'])){
                    $array['transport']['headers'] = ['Host' => array($server['network_settings']['headers']['Host'])];
                }
                $array['transport']['max_early_data'] = 2048;
                $array['transport']['early_data_header_name'] = 'Sec-WebSocket-Protocol';
            }
        };

        return $array;
    }

    public function token2Login(Request $request)
    {
        if ($request->input('token')) {
            $temphomeurl = config('v2board.app_url'); //也可修改为你需要的官网地址
            $homeurl = $temphomeurl."/#/login?verify=".$request->input('token')."&redirect=". ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
            return redirect()->to($homeurl)->send();
        }

    }

}
