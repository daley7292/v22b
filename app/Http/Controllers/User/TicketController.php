<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\TicketSave;
use App\Http\Requests\User\TicketWithdraw;
use App\Jobs\SendTelegramJob;
use App\Models\Plan;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\TicketService;
use App\Utils\Dict;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            $ticket = Ticket::where('id', $request->input('id'))
                ->where('user_id', $request->user['id'])
                ->first();
            if (!$ticket) {
                abort(500, __('Ticket does not exist'));
            }
            $ticket['message'] = TicketMessage::where('ticket_id', $ticket->id)->get();
            for ($i = 0; $i < count($ticket['message']); $i++) {
                if ($ticket['message'][$i]['user_id'] == $ticket->user_id) {
                    $ticket['message'][$i]['is_me'] = true;
                } else {
                    $ticket['message'][$i]['is_me'] = false;
                }
            }
            return response([
                'data' => $ticket
            ]);
        }
        $ticket = Ticket::where('user_id', $request->user['id'])
            ->orderBy('created_at', 'DESC')
            ->get();
        return response([
            'data' => $ticket
        ]);
    }

    public function save(TicketSave $request)
    {
        DB::beginTransaction();
        if ((int)Ticket::where('status', 0)->where('user_id', $request->user['id'])->lockForUpdate()->count()) {
            abort(500, __('There are other unresolved tickets'));
        }
        $ticket = Ticket::create(array_merge($request->only([
            'subject',
            'level'
        ]), [
            'user_id' => $request->user['id']
        ]));
        if (!$ticket) {
            DB::rollback();
            abort(500, __('Failed to open ticket'));
        }
        $ticketMessage = TicketMessage::create([
            'user_id' => $request->user['id'],
            'ticket_id' => $ticket->id,
            'message' => $request->input('message')
        ]);
        if (!$ticketMessage) {
            DB::rollback();
            abort(500, __('Failed to open ticket'));
        }
        DB::commit();

        //ISPInfo
        $ISPInfo = $this->getISPInfo($request->ip());

        //transfer
        $user = User::find($request->user['id']);
        $transferEnable = ($user->transfer_enable) / (1024*1024*1024);
        $transferUsed = ($user->u + $user->d) / (1024*1024*1024);
        $transferUsed = number_format($transferUsed, 2);

        //email
        $email = $user->email;

        //planName and expiredTime
        $planID = $user->plan_id;
        $plan = Plan::find($planID);
        $planName = '';
        $expiredTime = null;
        if ($plan) {
            $planName = $plan->name;
            $expiredTime = ($plan->onetime_price > 0)? "永不过期" : date('Y-m-d', $user->expired_at);
        }

        $this->sendNotify($ticket, $request->input('message'), $ISPInfo, $planName, $transferEnable, $transferUsed, $expiredTime, $email);

        return response([
            'data' => true
        ]);
    }

    public function reply(Request $request)
    {
        if (empty($request->input('id'))) {
            abort(500, __('Invalid parameter'));
        }
        if (empty($request->input('message'))) {
            abort(500, __('Message cannot be empty'));
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->where('user_id', $request->user['id'])
            ->first();
        if (!$ticket) {
            abort(500, __('Ticket does not exist'));
        }
        if ($ticket->status) {
            abort(500, __('The ticket is closed and cannot be replied'));
        }
        if ($request->user['id'] == $this->getLastMessage($ticket->id)->user_id) {
            abort(500, __('Please wait for the technical enginneer to reply'));
        }
        $ticketService = new TicketService();
        if (!$ticketService->reply(
            $ticket,
            $request->input('message'),
            $request->user['id']
        )) {
            abort(500, __('Ticket reply failed'));
        }

        //ISPInfo
        $ISPInfo = $this->getISPInfo($request->ip());

        //transfer
        $user = User::find($request->user['id']);
        $transferEnable = ($user->transfer_enable) / (1024*1024*1024);
        $transferUsed = ($user->u + $user->d) / (1024*1024*1024);
        $transferUsed = number_format($transferUsed, 2);

        //email
        $email = $user->email;

        //planName and expiredTime
        $planID = $user->plan_id;
        $plan = Plan::find($planID);
        $planName = '';
        $expiredTime = null;
        if ($plan) {
            $planName = $plan->name;
            $expiredTime = ($plan->onetime_price > 0)? "永不过期" : date('Y-m-d', $user->expired_at);
        }

        $this->sendNotify($ticket, $request->input('message'), $ISPInfo, $planName, $transferEnable, $transferUsed, $expiredTime, $email);

        return response([
            'data' => true
        ]);
    }


    public function close(Request $request)
    {
        if (empty($request->input('id'))) {
            abort(500, __('Invalid parameter'));
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->where('user_id', $request->user['id'])
            ->first();
        if (!$ticket) {
            abort(500, __('Ticket does not exist'));
        }
        $ticket->status = 1;
        if (!$ticket->save()) {
            abort(500, __('Close failed'));
        }
        return response([
            'data' => true
        ]);
    }

    private function getLastMessage($ticketId)
    {
        return TicketMessage::where('ticket_id', $ticketId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function withdraw(TicketWithdraw $request)
    {
        if ((int)config('v2board.withdraw_close_enable', 0)) {
            abort(500, 'user.ticket.withdraw.not_support_withdraw');
        }
        if (!in_array(
            $request->input('withdraw_method'),
            config(
                'v2board.commission_withdraw_method',
                Dict::WITHDRAW_METHOD_WHITELIST_DEFAULT
            )
        )) {
            abort(500, __('Unsupported withdrawal method'));
        }
        $user = User::find($request->user['id']);
        $limit = config('v2board.commission_withdraw_limit', 100);
        if ($limit > ($user->commission_balance / 100)) {
            abort(500, __('The current required minimum withdrawal commission is :limit', ['limit' => $limit]));
        }
        DB::beginTransaction();
        $subject = __('[Commission Withdrawal Request] This ticket is opened by the system');
        $ticket = Ticket::create([
            'subject' => $subject,
            'level' => 2,
            'user_id' => $request->user['id']
        ]);
        if (!$ticket) {
            DB::rollback();
            abort(500, __('Failed to open ticket'));
        }
        $message = sprintf("%s\r\n%s",
            __('Withdrawal method') . "：" . $request->input('withdraw_method'),
            __('Withdrawal account') . "：" . $request->input('withdraw_account')
        );
        $ticketMessage = TicketMessage::create([
            'user_id' => $request->user['id'],
            'ticket_id' => $ticket->id,
            'message' => $message
        ]);
        if (!$ticketMessage) {
            DB::rollback();
            abort(500, __('Failed to open ticket'));
        }
        DB::commit();


        //ISPInfo
        $ISPInfo = $this->getISPInfo($request->ip());

        //transfer
        $user = User::find($request->user['id']);
        $transferEnable = ($user->transfer_enable) / (1024*1024*1024);
        $transferUsed = ($user->u + $user->d) / (1024*1024*1024);
        $transferUsed = number_format($transferUsed, 2);

        //email
        $email = $user->email;

        //planName and expiredTime
        $planID = $user->plan_id;
        $plan = Plan::find($planID);
        $planName = '';
        $expiredTime = null;
        if ($plan) {
            $planName = $plan->name;
            $expiredTime = ($plan->onetime_price > 0)? "永不过期" : date('Y-m-d', $user->expired_at);
        }

        $this->sendNotify($ticket, $message, $ISPInfo, $planName, $transferEnable, $transferUsed, $expiredTime, $email);

        return response([
            'data' => true
        ]);
    }

    private function sendNotify(Ticket $ticket, string $message, $ISPInfo, $planName, $transferEnable, $transferUsed, $expiredTime, $email)
    {
        $telegramService = new TelegramService();
        $notification = "📮工单提醒 #{$ticket->id}\n"
            . "———————————————\n"
            . "邮箱：\n`{$email}`\n"
            . "运营商：\n`{$ISPInfo}`\n"
            . "套餐及流量：\n`{$planName}` | `{$transferUsed}` of `{$transferEnable}` GB\n"
            . "到期时间：\n`{$expiredTime}`\n"
            . "主题：\n`{$ticket->subject}`\n"
            . "内容：\n`{$message}`";

        $telegramService->sendMessageWithAdmin($notification, true);
    }

    private function getISPInfo($userIP){
        $ip2region = new \Ip2Region();
        try {
            return $ip2region->simple($userIP);
        } catch (\Exception $e) {
            // 处理异常情况
            // 可以输出错误信息或执行其他逻辑
            return "未知地区";
        }
    }
}
