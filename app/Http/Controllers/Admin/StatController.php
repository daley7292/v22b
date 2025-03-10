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
    // 验证请求参数
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date'
    ]);

    // 规范时间获取
    $endTime = strtotime($request->input('end_date') . ' 23:59:59');
    $startTime = strtotime($request->input('start_date') . ' 00:00:00');

    // 计算周期天数
    $periodDays = ceil(($endTime - $startTime) / 86400);

    // 一次性获取所有时间段的数据
    $data = Order::selectRaw('
            DATE_FORMAT(created_at, "%Y-%m-%d") as date,
            SUM(total_amount) as total_amount
        ')
        ->where('created_at', '>=', $startTime)
        ->where('created_at', '<=', $endTime)
        ->where('status', 3)
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get();

    // 获取各时期收入
    $currentIncome = $data->sum('total_amount');

    // 计算环比周期的时间范围（上一个相同时间长度）
    $previousStartTime = $startTime - ($endTime - $startTime);
    $previousEndTime = $previousStartTime + ($endTime - $startTime) - 1;

    // 获取环比周期收入
    $previousData = Order::selectRaw('
            DATE_FORMAT(created_at, "%Y-%m-%d") as date,
            SUM(total_amount) as total_amount
        ')
        ->where('created_at', '>=', $previousStartTime)
        ->where('created_at', '<=', $previousEndTime)
        ->where('status', 3)
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get();

    $previousIncome = $previousData->sum('total_amount');

    // 计算同比周期的时间范围（去年同期）
    $lastYearStartTime = strtotime('-1 year', $startTime);
    $lastYearEndTime = strtotime('-1 year', $endTime);

    // 获取同比周期收入
    $lastYearData = Order::selectRaw('
            DATE_FORMAT(created_at, "%Y-%m-%d") as date,
            SUM(total_amount) as total_amount
        ')
        ->where('created_at', '>=', $lastYearStartTime)
        ->where('created_at', '<=', $lastYearEndTime)
        ->where('status', 3)
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get();

    $lastYearIncome = $lastYearData->sum('total_amount');

    // 准备图表数据
    $chartData = $data->map(function ($item) use ($previousData, $lastYearData, $startTime, $endTime) {  // 添加 $startTime, $endTime
        $previousDay = $previousData->firstWhere('date', date('Y-m-d', strtotime($item->date) - ($endTime - $startTime)));  // 修改时间计算方式
        $lastYearDay = $lastYearData->firstWhere('date', date('Y-m-d', strtotime('-1 year', strtotime($item->date))));

        return [
            'date' => $item->date,
            'current' => $item->total_amount / 100,
            'previous' => $previousDay ? $previousDay->total_amount / 100 : 0,
            'lastYear' => $lastYearDay ? $lastYearDay->total_amount / 100 : 0,
        ];
    })->all();

    // 计算增长率
    $yearOnYear = $lastYearIncome > 0 ?
        round((($currentIncome - $lastYearIncome) / $lastYearIncome) * 100, 2) : 0;

    $chainRatio = $previousIncome > 0 ?
        round((($currentIncome - $previousIncome) / $previousIncome) * 100, 2) : 0;

    return [
        'data' => [
            'current_period' => [
                'start_date' => date('Y-m-d', $startTime),
                'end_date' => date('Y-m-d', $endTime),
                'income' => $currentIncome / 100,
                'days' => $periodDays
            ],
            'previous_period' => [
                'start_date' => date('Y-m-d', $previousStartTime),
                'end_date' => date('Y-m-d', $previousEndTime),
                'income' => $previousIncome / 100
            ],
            'last_year_period' => [
                'start_date' => date('Y-m-d', $lastYearStartTime),
                'end_date' => date('Y-m-d', $lastYearEndTime),
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

}

