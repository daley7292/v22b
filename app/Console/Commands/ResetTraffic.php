<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetTraffic extends Command
{
    protected $builder;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:traffic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '流量清空';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->builder = User::where('expired_at', '!=', NULL)
            ->where('expired_at', '>', time());
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', -1);
        $resetMethods = Plan::select(
            DB::raw("GROUP_CONCAT(`id`) as plan_ids"),
            DB::raw("reset_traffic_method as method")
        )
            ->groupBy('reset_traffic_method')
            ->get()
            ->toArray();
        foreach ($resetMethods as $resetMethod) {
            $planIds = explode(',', $resetMethod['plan_ids']);
            switch (true) {
                case ($resetMethod['method'] === NULL): {
                    $resetTrafficMethod = config('v2board.reset_traffic_method', 0);
                    $builder = with(clone($this->builder))->whereIn('plan_id', $planIds);
                    switch ((int)$resetTrafficMethod) {
                        // month first day
                        case 0:
                            $this->resetByMonthFirstDay($builder);
                            break;
                        // expire day
                        case 1:
                            $this->resetByExpireDay($builder);
                            break;
                        // no action
                        case 2:
                            break;
                        // year first day
                        case 3:
                            $this->resetByYearFirstDay($builder);
                            break;
                        // year expire day
                        case 4:
                            $this->resetByExpireYear($builder);
                            break;
                        // quarter first day
                        case 5:
                            $this->resetByQuarterFirstDay($builder);
                            break;
                        // half year first day
                        case 6:
                            $this->resetByHalfYearFirstDay($builder);
                            break;
                    }
                    break;
                }
                case ($resetMethod['method'] === 0): {
                    $builder = with(clone($this->builder))->whereIn('plan_id', $planIds);
                    $this->resetByMonthFirstDay($builder);
                    break;
                }
                case ($resetMethod['method'] === 1): {
                    $builder = with(clone($this->builder))->whereIn('plan_id', $planIds);
                    $this->resetByExpireDay($builder);
                    break;
                }
                case ($resetMethod['method'] === 2): {
                    break;
                }
                case ($resetMethod['method'] === 3): {
                    $builder = with(clone($this->builder))->whereIn('plan_id', $planIds);
                    $this->resetByYearFirstDay($builder);
                    break;
                }
                case ($resetMethod['method'] === 4): {
                    $builder = with(clone($this->builder))->whereIn('plan_id', $planIds);
                    $this->resetByExpireYear($builder);
                    break;
                }
                case ($resetMethod['method'] === 5): {
                    $builder = with(clone($this->builder))->whereIn('plan_id', $planIds);
                    $this->resetByQuarterFirstDay($builder);
                    break;
                }
                case ($resetMethod['method'] === 6): {
                    $builder = with(clone($this->builder))->whereIn('plan_id', $planIds);
                    $this->resetByHalfYearFirstDay($builder);
                    break;
                }
            }
        }
    }

    private function resetByExpireYear($builder):void
    {
        $users = [];
        foreach ($builder->get() as $item) {
            $expireDay = date('m-d', $item->expired_at);
            $today = date('m-d');
            if ($expireDay === $today) {
                array_push($users, $item->id);
            }
        }
        User::whereIn('id', $users)->update([
            'u' => 0,
            'd' => 0
        ]);
    }

    private function resetByYearFirstDay($builder):void
    {
        if ((string)date('md') === '0101') {
            $builder->update([
                'u' => 0,
                'd' => 0
            ]);
        }
    }

    private function resetByMonthFirstDay($builder):void
    {
        if ((string)date('d') === '01') {
            $builder->update([
                'u' => 0,
                'd' => 0
            ]);
        }
    }

    private function resetByExpireDay($builder):void
    {
        $lastDay = date('d', strtotime('last day of +0 months'));
        $users = [];
        foreach ($builder->get() as $item) {
            $expireDay = date('d', $item->expired_at);
            $today = date('d');
            if ($expireDay === $today) {
                array_push($users, $item->id);
            }

            if (($today === $lastDay) && $expireDay >= $lastDay) {
                array_push($users, $item->id);
            }
        }
        User::whereIn('id', $users)->update([
            'u' => 0,
            'd' => 0
        ]);
    }


    /**
 * 季度第一天重置流量
 * 每季度第一天（1月1日、4月1日、7月1日、10月1日）重置
 */
private function resetByQuarterFirstDay($builder):void
{
    $quarterFirstMonths = [1, 4, 7, 10]; // 季度的第一个月
    $currentMonth = (int)date('m');
    $currentDay = (string)date('d');
    
    if (in_array($currentMonth, $quarterFirstMonths) && $currentDay === '01') {
        $builder->update([
            'u' => 0,
            'd' => 0
        ]);
    }
}

/**
 * 半年第一天重置流量
 * 每半年第一天（1月1日、7月1日）重置
 */
private function resetByHalfYearFirstDay($builder):void
{
    $halfYearFirstMonths = [1, 7]; // 半年的第一个月
    $currentMonth = (int)date('m');
    $currentDay = (string)date('d');
    
    if (in_array($currentMonth, $halfYearFirstMonths) && $currentDay === '01') {
        $builder->update([
            'u' => 0,
            'd' => 0
        ]);
    }
}
}
