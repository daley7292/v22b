<?php

namespace App\Services;


use App\Http\Controllers\Passport\ApiController;
use App\Models\Payment;

class PaymentService
{
    public $method;
    protected $class;
    protected $config;
    protected $payment;

    public function __construct($method, $id = NULL, $uuid = NULL)
    {
        $this->method = $method;
        $this->class = '\\App\\Payments\\' . $this->method;
        if (!class_exists($this->class)) abort(500, 'gate is not found');
        if ($id) $payment = Payment::find($id)->toArray();
        if ($uuid) $payment = Payment::where('uuid', $uuid)->first()->toArray();
        $this->config = [];
        if (isset($payment)) {
            $this->config = $payment['config'];
            $this->config['enable'] = $payment['enable'];
            $this->config['id'] = $payment['id'];
            $this->config['uuid'] = $payment['uuid'];
            $this->config['notify_domain'] = $payment['notify_domain'];
        };
        $this->payment = new $this->class($this->config);
    }

    public function notify($params)
    {
        if (!$this->config['enable']) abort(500, 'gate is not enable');
        $result = $this->payment->notify($params);
        
         // 如果是订单且支付成功
         if (isset($result['status']) && $result['status'] === 3) {
            // 获取订单
            $order = \App\Models\Order::where('trade_no', $result['trade_no'])->first();
            if ($order) {
                try {
                    $inviteGiveType = (int)config('v2board.is_Invitation_to_give', 0);
                    // 模式2和模式3都需要处理订单支付后的赠送
                    if ($inviteGiveType === 2 || $inviteGiveType === 3) {
                        // 处理首单邀请奖励和佣金
                        $Api = new ApiController();
                        $Api->handleFirstOrderReward($order);
                    }
                } catch(\Exception $e) {
                    \Log::error('处理首单邀请奖励失败', [
                        'error' => $e->getMessage(),
                        'order_id' => $order->id,
                        'trade_no' => $order->trade_no
                    ]);
                }
            }
            
        }
        return $result;
    }

    public function pay($order)
    {
        // custom notify domain name
        $notifyUrl = url("/api/v1/guest/payment/notify/{$this->method}/{$this->config['uuid']}");
        if ($this->config['notify_domain']) {
            $parseUrl = parse_url($notifyUrl);
            $notifyUrl = $this->config['notify_domain'] . $parseUrl['path'];
        }

        return $this->payment->pay([
            'notify_url' => $notifyUrl,
            'return_url' => config('v2board.app_url') . '/#/order/' . $order['trade_no'],
            'trade_no' => $order['trade_no'],
            'total_amount' => $order['total_amount'],
            'user_id' => $order['user_id'],
            'stripe_token' => $order['stripe_token']
        ]);
    }

    public function form()
    {
        $form = $this->payment->form();
        $keys = array_keys($form);
        foreach ($keys as $key) {
            if (isset($this->config[$key])) $form[$key]['value'] = $this->config[$key];
        }
        return $form;
    }
}
