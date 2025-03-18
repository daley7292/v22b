<?php

namespace App\Console\Commands;

use App\Models\CommissionLog;
use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:commission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '返佣服务';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->autoCheck();
        $this->autoPayCommission();
    }

    public function autoCheck()
    {
        if ((int)config('v2board.commission_auto_check_enable', 1)) {
            Order::where('commission_status', 0)
                ->where('invite_user_id', '!=', NULL)
                ->where('status', 3)
                ->where('updated_at', '<=', strtotime('-3 day', time()))
                ->update([
                    'commission_status' => 1
                ]);
        }
    }

    public function autoPayCommission()
    {
        $orders = Order::where('commission_status', 1)
            ->where('invite_user_id', '!=', NULL)
            ->get();
        foreach ($orders as $order) {
            DB::beginTransaction();
            if (!$this->payHandle($order->invite_user_id, $order)) {
                DB::rollBack();
                continue;
            }
            $order->commission_status = 2;
            if (!$order->save()) {
                DB::rollBack();
                continue;
            }
            DB::commit();
        }
    }

    public function payHandle($inviteUserId, Order $order)
    {
        $level = 3;
        
        // 1. 计算实际支付金额（单位：分）
        $actualPaidAmount = $order->total_amount 
            - ($order->discount_amount ?? 0)
            - ($order->surplus_amount ?? 0)
            - ($order->balance_amount ?? 0);
        
        if ($actualPaidAmount <= 0) {
            return false;
        }

        // 2. 获取佣金比例
        $commissionRate = config('v2board.commission_rate', 10); // 默认10%
        $baseCommissionRate = $commissionRate / 100;
        
        // 3. 计算实际佣金基数
        $actualCommissionBase = (int)round($actualPaidAmount * $baseCommissionRate);

        // 4. 获取分销配置
        $commissionShareLevels = (int)config('v2board.commission_distribution_enable', 0) 
            ? [
                0 => (int)config('v2board.commission_distribution_l1'),
                1 => (int)config('v2board.commission_distribution_l2'),
                2 => (int)config('v2board.commission_distribution_l3')
            ] 
            : [0 => 100];

        $totalCommissionPaid = 0;

        // 5. 处理每级分销
        for ($l = 0; $l < $level; $l++) {
            $inviter = User::find($inviteUserId);
            if (!$inviter || !isset($commissionShareLevels[$l])) {
                continue;
            }

            $commissionBalance = (int)round($actualCommissionBase * ($commissionShareLevels[$l] / 100));
            if ($commissionBalance <= 0) {
                continue;
            }

            // 6. 更新邀请人余额
            if ((int)config('v2board.withdraw_close_enable', 0)) {
                $inviter->balance += $commissionBalance;
            } else {
                $inviter->commission_balance += $commissionBalance;
            }

            if (!$inviter->save()) {
                return false;
            }

            // 7. 记录佣金日志
            if (!$this->createCommissionLog($inviter, $order, $actualPaidAmount, $commissionBalance, $baseCommissionRate)) {
                return false;
            }

            $inviteUserId = $inviter->invite_user_id;
            $totalCommissionPaid += $commissionBalance;
        }

        // 8. 更新订单实际佣金
        $order->actual_commission_balance = $totalCommissionPaid;
        return $order->save();
    }

    private function createCommissionLog($inviter, $order, $actualPaidAmount, $commissionBalance, $baseCommissionRate)
    {
        return CommissionLog::create([
            'invite_user_id' => $inviter->id,
            'user_id' => $order->user_id,
            'trade_no' => $order->trade_no,
            'order_amount' => $actualPaidAmount,
            'get_amount' => $commissionBalance,
            'note' => sprintf(
                '订单金额：%.2f，优惠：%.2f，实付：%.2f，佣金率：%.2f%%',
                $order->total_amount / 100,
                ($order->discount_amount ?? 0) / 100,
                $actualPaidAmount / 100,
                $baseCommissionRate * 100
            )
        ]);
    }

}
