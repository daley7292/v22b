<?php

namespace App\Http\Controllers\Admin;

use App\Models\CommissionLog;
use App\Models\ServerShadowsocks;
use App\Models\ServerTrojan;
use App\Models\StatUser;
use App\Services\ServerService;
use App\Services\StatisticalService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ServerGroup;
use App\Models\ServerVmess;
use App\Models\Plan;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Order;
use App\Models\Stat;
use App\Models\StatServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatController extends Controller
{
    public function getStat(Request $request)
    {
        $params = $request->validate([
            'start_at' => '',
            'end_at' => ''
        ]);

        if (isset($params['start_at']) && isset($params['end_at'])) {
            $stats = Stat::where('record_at', '>=', $params['start_at'])
                ->where('record_at', '<', $params['end_at'])
                ->get()
                ->makeHidden(['record_at', 'created_at', 'updated_at', 'id', 'record_type'])
                ->toArray();
        } else {
            $statisticalService = new StatisticalService();
            return [
                'data' => $statisticalService->generateStatData()
            ];
        }

        $stats = array_reduce($stats, function($carry, $item) {
            foreach($item as $key => $value) {
                if(isset($carry[$key]) && $carry[$key]) {
                    $carry[$key] += $value;
                } else {
                    $carry[$key] = $value;
                }
            }
            return $carry;
        }, []);

        return [
            'data' => $stats
        ];
    }

    public function getStatRecord(Request $request)
    {
        $request->validate([
            'type' => 'required|in:paid_total,commission_total,register_count',
            'start_at' => '',
            'end_at' => ''
        ]);

        $statisticalService = new StatisticalService();
        $statisticalService->setStartAt($request->input('start_at'));
        $statisticalService->setEndAt($request->input('end_at'));
        return [
            'data' => $statisticalService->getStatRecord($request->input('type'))
        ];
    }

    public function getRanking(Request $request)
    {
        $request->validate([
            'type' => 'required|in:server_traffic_rank,user_consumption_rank,invite_rank',
            'start_at' => '',
            'end_at' => '',
            'limit' => 'nullable|integer'
        ]);

        $statisticalService = new StatisticalService();
        $statisticalService->setStartAt($request->input('start_at'));
        $statisticalService->setEndAt($request->input('end_at'));
        return [
            'data' => $statisticalService->getRanking($request->input('type'), $request->input('limit') ?? 20)
        ];
    }

    public function getOverride(Request $request)
    {
        return [
            'data' => [
                'month_income' => Order::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', time())
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'month_register_total' => User::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', time())
                    ->count(),
                'ticket_pending_total' => Ticket::where('status', 0)
                    ->count(),
                'commission_pending_total' => Order::where('commission_status', 0)
                    ->where('invite_user_id', '!=', NULL)
                    ->whereNotIn('status', [0, 2])
                    ->where('commission_balance', '>', 0)
                    ->count(),
                'day_income' => Order::where('created_at', '>=', strtotime(date('Y-m-d')))
                    ->where('created_at', '<', time())
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'last_month_income' => Order::where('created_at', '>=', strtotime('-1 month', strtotime(date('Y-m-1'))))
                    ->where('created_at', '<', strtotime(date('Y-m-1')))
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'commission_month_payout' => CommissionLog::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', time())
                    ->sum('get_amount'),
                'commission_last_month_payout' => CommissionLog::where('created_at', '>=', strtotime('-1 month', strtotime(date('Y-m-1'))))
                    ->where('created_at', '<', strtotime(date('Y-m-1')))
                    ->sum('get_amount'),
            ]
        ];
    }

    public function getOrder(Request $request)
    {
                    // 计算本周和上周的时间范围
                    $thisWeekStart = strtotime('monday this week');
                    $thisWeekEnd = time();
                    $lastWeekStart = strtotime('monday last week');
                    $lastWeekEnd = strtotime('sunday last week');
            
                    // 获取本周和上周的新购数据
                    $thisWeekNewPurchase = Order::where('created_at', '>=', $thisWeekStart)
                        ->where('created_at', '<=', $thisWeekEnd)
                        ->where('type', 1)
                        ->where('status', 3)
                        ->count();
            
                    $lastWeekNewPurchase = Order::where('created_at', '>=', $lastWeekStart)
                        ->where('created_at', '<=', $lastWeekEnd)
                        ->where('type', 1)
                        ->where('status', 3)
                        ->count();
            
                    // 获取本周和上周的续费数据
                    $thisWeekRenew = Order::where('created_at', '>=', $thisWeekStart)
                        ->where('created_at', '<=', $thisWeekEnd)
                        ->where('type', 2)
                        ->where('status', 3)
                        ->count();
            
                    $lastWeekRenew = Order::where('created_at', '>=', $lastWeekStart)
                        ->where('created_at', '<=', $lastWeekEnd)
                        ->where('type', 2)
                        ->where('status', 3)
                        ->count();
            
                    // 计算增长率和流失率
                    $newPurchaseRate = $lastWeekNewPurchase > 0 ? 
                        round(($thisWeekNewPurchase / $lastWeekNewPurchase) * 100, 2) : 0;
                    $renewRate = $lastWeekRenew > 0 ? 
                        round(($thisWeekRenew / $lastWeekRenew) * 100, 2) : 0;
            
                    // 获取每日数据用于图表展示
                    $chartData = [];    
            
                    // 获取上周每日数据
                for ($i = 0; $i < 7; $i++) {
                    $dayStart = $lastWeekStart + ($i * 86400);
                    $dayEnd = $dayStart + 86399;
                    $date = date('m-d', $dayStart);
                    
                    $dayNewPurchase = Order::where('created_at', '>=', $dayStart)
                        ->where('created_at', '<=', $dayEnd)
                        ->where('type', 1)
                        ->where('status', 3)
                        ->count();
                        
                    $dayRenew = Order::where('created_at', '>=', $dayStart)
                        ->where('created_at', '<=', $dayEnd)
                        ->where('type', 2)
                        ->where('status', 3)
                        ->count();
                        
                    $chartData[] = [
                        'date' => $date,
                        'type' => '上周新购',
                        'value' => $dayNewPurchase
                    ];
                    $chartData[] = [
                        'date' => $date,
                        'type' => '上周续费',
                        'value' => $dayRenew
                    ];
                }
                
                // 获取本周每日数据
                for ($i = 0; $i < 7; $i++) {
                    $dayStart = $thisWeekStart + ($i * 86400);
                    if ($dayStart > time()) break;
                    
                    $dayEnd = min($dayStart + 86399, time());
                    $date = date('m-d', $dayStart);
                    
                    $dayNewPurchase = Order::where('created_at', '>=', $dayStart)
                        ->where('created_at', '<=', $dayEnd)
                        ->where('type', 1)
                        ->where('status', 3)
                        ->count();
                        
                    $dayRenew = Order::where('created_at', '>=', $dayStart)
                        ->where('created_at', '<=', $dayEnd)
                        ->where('type', 2)
                        ->where('status', 3)
                        ->count();
                        
                    $chartData[] = [
                        'date' => $date,
                        'type' => '本周新购',
                        'value' => $dayNewPurchase
                    ];
                    $chartData[] = [
                        'date' => $date,
                        'type' => '本周续费',
                        'value' => $dayRenew
                    ];
                }
        
        $statistics = Stat::where('record_type', 'd')
            ->limit(31)
            ->orderBy('record_at', 'DESC')
            ->get()
            ->toArray();
        $result = [];
        foreach ($statistics as $statistic) {
            $date = date('m-d', $statistic['record_at']);
            $result[] = [
                'type' => '收款金额',
                'date' => $date,
                'value' => $statistic['paid_total'] / 100
            ];
            $result[] = [
                'type' => '收款笔数',
                'date' => $date,
                'value' => $statistic['paid_count']
            ];
            $result[] = [
                'type' => '佣金金额(已发放)',
                'date' => $date,
                'value' => $statistic['commission_total'] / 100
            ];
            $result[] = [
                'type' => '佣金笔数(已发放)',
                'date' => $date,
                'value' => $statistic['commission_count']
            ];

                // 获取当天的开始和结束时间
                $dayStart = strtotime(date('Y-m-d', $statistic['record_at']));
                $dayEnd = $dayStart + 86399;

                // 获取新购数据
                $newPurchaseDay = Order::where('created_at', '>=', $dayStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 1)
                    ->where('status', 3)
                    ->count();

                // 获取续费数据
                $renewDay = Order::where('created_at', '>=', $dayStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 2)
                    ->where('status', 3)
                    ->count();

                // 添加日数据
                $result[] = [
                    'type' => '日新购',
                    'date' => $date,
                    'value' => $newPurchaseDay
                ];
                $result[] = [
                    'type' => '日续费',
                    'date' => $date,
                    'value' => $renewDay
                ];

                // 获取周数据（过去7天）
                $weekStart = $dayStart - 86400 * 6;
                $newPurchaseWeek = Order::where('created_at', '>=', $weekStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 1)
                    ->where('status', 3)
                    ->count();
                $renewWeek = Order::where('created_at', '>=', $weekStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 2)
                    ->where('status', 3)
                    ->count();

                // 获取月数据
                $monthStart = strtotime(date('Y-m-01', $statistic['record_at']));
                $newPurchaseMonth = Order::where('created_at', '>=', $monthStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 1)
                    ->where('status', 3)
                    ->count();
                $renewMonth = Order::where('created_at', '>=', $monthStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 2)
                    ->where('status', 3)
                    ->count();

                // 获取半年数据
                $halfYearStart = strtotime('-6 months', $dayStart);
                $newPurchaseHalfYear = Order::where('created_at', '>=', $halfYearStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 1)
                    ->where('status', 3)
                    ->count();
                $renewHalfYear = Order::where('created_at', '>=', $halfYearStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 2)
                    ->where('status', 3)
                    ->count();

                // 获取年数据
                $yearStart = strtotime('-1 year', $dayStart);
                $newPurchaseYear = Order::where('created_at', '>=', $yearStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 1)
                    ->where('status', 3)
                    ->count();
                $renewYear = Order::where('created_at', '>=', $yearStart)
                    ->where('created_at', '<=', $dayEnd)
                    ->where('type', 2)
                    ->where('status', 3)
                    ->count();

                // 添加周、月、半年、年数据
                $result[] = [
                    'type' => '周新购',
                    'date' => $date,
                    'value' => $newPurchaseWeek
                ];
                $result[] = [
                    'type' => '周续费',
                    'date' => $date,
                    'value' => $renewWeek
                ];
                $result[] = [
                    'type' => '月新购',
                    'date' => $date,
                    'value' => $newPurchaseMonth
                ];
                $result[] = [
                    'type' => '月续费',
                    'date' => $date,
                    'value' => $renewMonth
                ];
                $result[] = [
                    'type' => '半年新购',
                    'date' => $date,
                    'value' => $newPurchaseHalfYear
                ];
                $result[] = [
                    'type' => '半年续费',
                    'date' => $date,
                    'value' => $renewHalfYear
                ];
                $result[] = [
                    'type' => '年新购',
                    'date' => $date,
                    'value' => $newPurchaseYear
                ];
                $result[] = [
                    'type' => '年续费',
                    'date' => $date,
                    'value' => $renewYear
                ];
        }

        $result = array_reverse($result);
        return [
            'data' => [
                'statistics' => $result,
                'comparison' => [
                    'new_purchase' => [
                        'this_week' => $thisWeekNewPurchase,
                        'last_week' => $lastWeekNewPurchase,
                        'growth_rate' => $newPurchaseRate,
                        'growth_text' => "当前新购率是上周的{$newPurchaseRate}%"
                    ],
                    'renew' => [
                        'this_week' => $thisWeekRenew,
                        'last_week' => $lastWeekRenew,
                        'churn_rate' => $renewRate,
                        'churn_text' => "当前用户流失率是上周的{$renewRate}%"
                    ]
                ],
                'chart_data' => $chartData
            ]
        ];
    }
/**
 * 获取财务统计数据
 * @param Request $request
 * @return array
 */
public function getFinances(Request $request)
{
    // 1. 请求参数验证
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'user_id' => 'nullable|integer'
    ]);

    // 2. 时间处理
    $endTime = strtotime($request->input('end_date') . ' 23:59:59');
    $startTime = strtotime($request->input('start_date') . ' 00:00:00');
    $periodDays = ceil(($endTime - $startTime) / 86400);

    // 3. 基础查询构建
    $baseQuery = Order::query()->where('status', 3);
    
    // 4. 用户数据查询（新增功能）
    $userStats = null;
    if ($request->filled('user_id')) {
        $userId = $request->input('user_id');
        $userStats = $this->getUserStatistics($userId);
        
        // 修改基础查询，加入用户条件
        $baseQuery->where(function($query) use ($userId) {
            $query->where('user_id', $userId)
                ->orWhere('invite_user_id', $userId);
        });
    }

    // 5. 保留原有的时期数据查询
    $data = $this->getPeriodData($baseQuery, $startTime, $endTime);
    
    // 6. 获取环比数据
    $previousData = $this->getPreviousPeriodData($startTime, $endTime);
    
    // 7. 获取同比数据
    $lastYearData = $this->getLastYearPeriodData($startTime, $endTime);
    
    // 8. 计算各项指标
    $currentIncome = $data->sum('total_amount');
    $previousIncome = $previousData->sum('total_amount');
    $lastYearIncome = $lastYearData->sum('total_amount');
    
    // 9. 准备图表数据
    $chartData = $this->prepareChartData($data, $previousData, $lastYearData, $startTime, $endTime);
    
    // 10. 计算增长率
    $yearOnYear = $this->calculateGrowthRate($currentIncome, $lastYearIncome);
    $chainRatio = $this->calculateGrowthRate($currentIncome, $previousIncome);

    // 11. 构建响应数据
    $response = [
        'data' => [
            'current_period' => [
                'start_date' => date('Y-m-d', $startTime),
                'end_date' => date('Y-m-d', $endTime),
                'income' => $currentIncome / 100,
                'days' => $periodDays
            ],
            'previous_period' => [
                'start_date' => date('Y-m-d', $startTime - ($endTime - $startTime)),
                'income' => $previousIncome / 100
            ],
            'last_year_period' => [
                'start_date' => date('Y-m-d', strtotime('-1 year', $startTime)),
                'end_date' => date('Y-m-d', strtotime('-1 year', $endTime)),
                'income' => $lastYearIncome / 100
            ],
            'comparison' => [
                'chain_ratio' => $chainRatio,
                'chain_text' => $chainRatio >= 0 ? 
                    "环比增长{$chainRatio}%" : 
                    "环比下降" . abs($chainRatio) . "%",
                'year_on_year' => $yearOnYear,
                'year_text' => $yearOnYear >= 0 ? 
                    "同比增长{$yearOnYear}%" : 
                    "同比下降" . abs($yearOnYear) . "%"
            ],
            'chart_data' => $chartData
        ]
    ];

    // 12. 添加用户统计数据（如果有）
    if ($userStats) {
        $response['data']['user_statistics'] = $userStats;
    }

    return $response;
}

// 新增辅助方法
private function getUserStatistics($userId)
{
    $now = time();
    $ranges = [
        'week' => [strtotime('-1 week', $now), $now],
        'month' => [strtotime('-1 month', $now), $now],
        'quarter' => [strtotime('-3 month', $now), $now],
        'half_year' => [strtotime('-6 month', $now), $now],
        'year' => [strtotime('-1 year', $now), $now]
    ];

    $userStats = [
        'user_info' => User::select(['id', 'email', 'commission_rate', 'commission_balance'])
            ->where('id', $userId)
            ->first(),
        'periods' => []
    ];

    foreach ($ranges as $period => $range) {
        $userStats['periods'][$period] = [
            'invited_users' => User::where('invite_user_id', $userId)
                ->whereBetween('created_at', $range)
                ->count(),
            'new_purchase' => Order::where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('invite_user_id', $userId);
            })
            ->where('type', 1)
            ->where('status', 3)
            ->whereBetween('created_at', $range)
            ->count(),
            'renew' => Order::where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('invite_user_id', $userId);
            })
            ->where('type', 2)
            ->where('status', 3)
            ->whereBetween('created_at', $range)
            ->count(),
            'total_amount' => Order::where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('invite_user_id', $userId);
            })
            ->where('status', 3)
            ->whereBetween('created_at', $range)
            ->sum('total_amount') / 100,
            'commission_amount' => Order::where('invite_user_id', $userId)
                ->where('status', 3)
                ->whereBetween('created_at', $range)
                ->sum('commission_balance') / 100
        ];
    }

    return $userStats;
}

    public function getServerLastRank()
    {
        $servers = [
            'shadowsocks' => ServerShadowsocks::where('parent_id', null)->get()->toArray(),
            'v2ray' => ServerVmess::where('parent_id', null)->get()->toArray(),
            'trojan' => ServerTrojan::where('parent_id', null)->get()->toArray(),
            'vmess' => ServerVmess::where('parent_id', null)->get()->toArray()
        ];
        $startAt = strtotime('-1 day', strtotime(date('Y-m-d')));
        $endAt = strtotime(date('Y-m-d'));
        $statistics = StatServer::select([
            'server_id',
            'server_type',
            'u',
            'd',
            DB::raw('(u+d) as total')
        ])
            ->where('record_at', '>=', $startAt)
            ->where('record_at', '<', $endAt)
            ->where('record_type', 'd')
            ->limit(10)
            ->orderBy('total', 'DESC')
            ->get()
            ->toArray();
        foreach ($statistics as $k => $v) {
            foreach ($servers[$v['server_type']] as $server) {
                if ($server['id'] === $v['server_id']) {
                    $statistics[$k]['server_name'] = $server['name'];
                }
            }
            $statistics[$k]['total'] = $statistics[$k]['total'] / 1073741824;
        }
        array_multisort(array_column($statistics, 'total'), SORT_DESC, $statistics);
        return [
            'data' => $statistics
        ];
    }

    public function getStatUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $builder = StatUser::orderBy('record_at', 'DESC')->where('user_id', $request->input('user_id'));

        $total = $builder->count();
        $records = $builder->forPage($current, $pageSize)
            ->get();
        return [
            'data' => $records,
            'total' => $total
        ];
    }



    /**
 * 获取指定时期的数据
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @param int $startTime
 * @param int $endTime
 * @return \Illuminate\Support\Collection
 */
private function getPeriodData($query, $startTime, $endTime)
{
    return (clone $query)
        ->selectRaw('DATE(FROM_UNIXTIME(created_at)) as date, SUM(total_amount) as total_amount')
        ->where('created_at', '>=', $startTime)
        ->where('created_at', '<=', $endTime)
        ->groupBy('date')
        ->orderBy('date')
        ->get();
}

/**
 * 获取环比时期的数据
 * @param int $startTime
 * @param int $endTime
 * @return \Illuminate\Support\Collection
 */
private function getPreviousPeriodData($startTime, $endTime)
{
    $periodLength = $endTime - $startTime;
    $previousStartTime = $startTime - $periodLength;
    $previousEndTime = $endTime - $periodLength;
    
    return Order::where('status', 3)
        ->selectRaw('DATE(FROM_UNIXTIME(created_at)) as date, SUM(total_amount) as total_amount')
        ->where('created_at', '>=', $previousStartTime)
        ->where('created_at', '<=', $previousEndTime)
        ->groupBy('date')
        ->orderBy('date')
        ->get();
}

/**
 * 获取同比时期的数据
 * @param int $startTime
 * @param int $endTime
 * @return \Illuminate\Support\Collection
 */
private function getLastYearPeriodData($startTime, $endTime)
{
    $lastYearStartTime = strtotime('-1 year', $startTime);
    $lastYearEndTime = strtotime('-1 year', $endTime);
    
    return Order::where('status', 3)
        ->selectRaw('DATE(FROM_UNIXTIME(created_at)) as date, SUM(total_amount) as total_amount')
        ->where('created_at', '>=', $lastYearStartTime)
        ->where('created_at', '<=', $lastYearEndTime)
        ->groupBy('date')
        ->orderBy('date')
        ->get();
}

/**
 * 准备图表数据
 * @param \Illuminate\Support\Collection $currentData
 * @param \Illuminate\Support\Collection $previousData
 * @param \Illuminate\Support\Collection $lastYearData
 * @param int $startTime
 * @param int $endTime
 * @return array
 */
private function prepareChartData($currentData, $previousData, $lastYearData, $startTime, $endTime)
{
    $chartData = [];
    $current = $startTime;
    
    while ($current <= $endTime) {
        $date = date('Y-m-d', $current);
        $previousDate = date('Y-m-d', $current - ($endTime - $startTime));
        $lastYearDate = date('Y-m-d', strtotime('-1 year', $current));
        
        $chartData[] = [
            'date' => $date,
            'current' => ($currentData->firstWhere('date', $date)->total_amount ?? 0) / 100,
            'previous' => ($previousData->firstWhere('date', $previousDate)->total_amount ?? 0) / 100,
            'lastYear' => ($lastYearData->firstWhere('date', $lastYearDate)->total_amount ?? 0) / 100
        ];
        
        $current = strtotime('+1 day', $current);
    }
    
    return $chartData;
}

/**
 * 计算增长率
 * @param float $current
 * @param float $previous
 * @return float
 */
private function calculateGrowthRate($current, $previous)
{
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round(($current - $previous) / $previous * 100, 2);
}




/**
 * 获取在线用户统计数据
 * @return array
 */
public function getOnlinePresence()
{

 
       // 获取当前时间戳作为基准时间
       $baseTime = time();
        
       // 定义时间范围
       $timeRanges = [
           'current' => $baseTime - 600, // 最近10分钟
           'today' => strtotime('today'),
           'three_days' => $baseTime - (3 * 86400),
           'seven_days' => $baseTime - (7 * 86400),
           'fifteen_days' => $baseTime - (15 * 86400),
           'thirty_days' => $baseTime - (30 * 86400)
       ];

       // 构建基础查询 - 只查询有流量变化的记录
       $baseQuery = StatUser::where(function($query) {
           $query->where('u', '>', 0)
               ->orWhere('d', '>', 0);
       });

       // 获取各时间段的活跃用户数
       $stats = [
           'current_online' => (clone $baseQuery)
               ->where('created_at', '>=', $timeRanges['current'])
               ->distinct('user_id')
               ->count('user_id'),
           'today_online' => (clone $baseQuery)
               ->where('created_at', '>=', $timeRanges['today'])
               ->distinct('user_id')
               ->count('user_id'),
           'three_days_online' => (clone $baseQuery)
               ->where('created_at', '>=', $timeRanges['three_days'])
               ->distinct('user_id')
               ->count('user_id'),
           'seven_days_online' => (clone $baseQuery)
               ->where('created_at', '>=', $timeRanges['seven_days'])
               ->distinct('user_id')
               ->count('user_id'),
           'fifteen_days_online' => (clone $baseQuery)
               ->where('created_at', '>=', $timeRanges['fifteen_days'])
               ->distinct('user_id')
               ->count('user_id'),
           'thirty_days_online' => (clone $baseQuery)
               ->where('created_at', '>=', $timeRanges['thirty_days'])
               ->distinct('user_id')
               ->count('user_id')
       ];

       // 添加调试信息
       $debug = [
           'current_time' => date('Y-m-d H:i:s', $baseTime),
           'time_ranges' => array_map(function($timestamp) {
               return date('Y-m-d H:i:s', $timestamp);
           }, $timeRanges),
           'query_conditions' => [
               'current_online_start' => date('Y-m-d H:i:s', $timeRanges['current']),
               'has_traffic' => 'u > 0 OR d > 0'
           ]
       ];

       return [
           'data' => [
               'statistics' => $stats,
            //   'debug' => $debug,
               'last_updated' => date('Y-m-d H:i:s')
           ]
       ];
    
}

/**
 * 获取指定时间段内的在线用户数
 * @param int $startTime
 * @return int
 */
private function getOnlineCount($startTime)
{
    return StatUser::where('created_at', '>=', $startTime)
        ->distinct('user_id')
        ->count('user_id');
}



/**
 * 获取节点流量统计
 * @return array
 */
public function getNodalFlow()
{
    try {
        // 获取当前时间戳作为基准时间
        $baseTime = time();
        
        // 定义时间范围
        $timeRanges = [
            'today' => [strtotime('today'), $baseTime],
            'week' => [strtotime('-7 days'), $baseTime],
            'half_month' => [strtotime('-15 days'), $baseTime],
            'month' => [strtotime('-30 days'), $baseTime],
            'quarter' => [strtotime('-90 days'), $baseTime],
            'half_year' => [strtotime('-180 days'), $baseTime],
            'year' => [strtotime('-365 days'), $baseTime]
        ];

        // 获取所有服务器信息
        $servers = [
            'hysteria' => DB::table('v2_server_hysteria')->select(['id', 'name'])->get()->keyBy('id'),
            'shadowsocks' => DB::table('v2_server_shadowsocks')->select(['id', 'name'])->get()->keyBy('id'),
            'trojan' => DB::table('v2_server_trojan')->select(['id', 'name'])->get()->keyBy('id'),
            'vmess' => DB::table('v2_server_vmess')->select(['id', 'name'])->get()->keyBy('id')
        ];

        // 初始化结果数组
        $result = [];
        
        // 遍历每个时间范围获取统计数据
        foreach ($timeRanges as $period => $range) {
            // 获取该时间范围内的流量数据
            $stats = StatServer::select([
                'server_id',
                'server_type',
                DB::raw('SUM(u + d) as total_traffic')
            ])
            ->whereBetween('record_at', $range)
            ->groupBy('server_id', 'server_type')
            ->get();

            // 处理统计数据
            $periodStats = [];
            foreach ($stats as $stat) {
                // 获取对应服务器信息
                $serverInfo = $servers[$stat->server_type][$stat->server_id] ?? null;
                if (!$serverInfo) continue;

                $periodStats[] = [
                    'server_id' => $stat->server_id,
                    'server_name' => $serverInfo->name,
                    'server_type' => $stat->server_type,
                    'traffic' => [
                        'bytes' => $stat->total_traffic,
                        'mb' => round($stat->total_traffic / 1024 / 1024, 2),
                        'gb' => round($stat->total_traffic / 1024 / 1024 / 1024, 2)
                    ]
                ];
            }

            // 按流量降序排序
            usort($periodStats, function($a, $b) {
                return $b['traffic']['bytes'] - $a['traffic']['bytes'];
            });

            $result[$period] = [
                'time_range' => [
                    'start' => date('Y-m-d H:i:s', $range[0]),
                    'end' => date('Y-m-d H:i:s', $range[1])
                ],
                'total_traffic' => array_sum(array_column(array_column($periodStats, 'traffic'), 'bytes')),
                'servers' => $periodStats
            ];
        }

        // 计算总计数据
        $totalStats = [
            'total_servers' => count($stats),
            'total_traffic' => array_sum(array_column($result, 'total_traffic')),
            'server_types' => [
                'hysteria' => count($servers['hysteria']),
                'shadowsocks' => count($servers['shadowsocks']),
                'trojan' => count($servers['trojan']),
                'vmess' => count($servers['vmess'])
            ]
        ];

        return [
            'data' => [
                'statistics' => $result,
                'summary' => $totalStats,
                'last_updated' => date('Y-m-d H:i:s')
            ]
        ];

    } catch (\Exception $e) {
        \Log::error('获取节点流量统计失败:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'data' => [
                'error' => '获取统计数据失败: ' . $e->getMessage(),
                'last_updated' => date('Y-m-d H:i:s')
            ]
        ];
    }
}


}

