<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UserFetch;
use App\Http\Requests\Admin\UserGenerate;
use App\Http\Requests\Admin\UserSendMail;
use App\Http\Requests\Admin\UserUpdate;
use App\Jobs\SendEmailJob;
use App\Services\AuthService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDel;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function resetSecret(Request $request)
    {
        $user = User::find($request->input('id'));
        if (!$user) abort(500, '用户不存在');
        $user->token = Helper::guid();
        $user->uuid = Helper::guid(true);
        return response([
            'data' => $user->save()
        ]);
    }

    private function filter(Request $request, $builder)
    {
        $filters = $request->input('filter');
        if ($filters) {
            foreach ($filters as $k => $filter) {

                // 特殊处理 expire_days
                if ($filter['key'] === 'expire_days') {
                    $expireDays = (int)$filter['value'];
                    $targetTimestamp = strtotime("+{$expireDays} days");
                    $builder->where('expired_at', '<=', $targetTimestamp)
                           ->where('expired_at', '>', 0);
                    continue;
                }
                
                // 处理余额和佣金字段
                if ($filter['key'] === 'balance' || $filter['key'] === 'commission_balance') {
                    $value = (float)$filter['value'] * 100; // 转换为分
                    $builder->where($filter['key'], $filter['condition'], $value);
                    continue;
                }
                
                // 处理其他条件
                if ($filter['condition'] === '模糊') {
                    $filter['condition'] = 'like';
                    $filter['value'] = "%{$filter['value']}%";
                }
                if ($filter['condition'] === '为空') {
                    $filter['condition'] = 'like';
                    $filter['value'] = "%{$filter['value']}%";
                }
                if ($filter['key'] === 'd' || $filter['key'] === 'transfer_enable') {
                    $filter['value'] = $filter['value'] * 1073741824;
                }

                if ($filter['key'] === 'invite_by_email') {
                    //var_dump($filter['value']);exit;
                    $value = trim($filter['value'], '%');
                    if ($value == -1) {
                        // 查询非空邀请用户
                        $builder->where('invite_user_id', '>', 0);
                        continue;
                    } else if ($value == -2) {
                        // 查询无邀请用户
                        $builder->where(function($query) {
                            $query->whereNull('invite_user_id')
                                  ->orWhere('invite_user_id', 0);  // 如果有使用0表示无邀请
                        });
                        continue;
                    } else {
                        // 按邮箱查询邀请人
                        $user = User::where('email', $filter['condition'], $filter['value'])->first();
                        $inviteUserId = isset($user->id) ? $user->id : 0;
                        $builder->where('invite_user_id', $inviteUserId);
                        unset($filters[$k]);
                        continue;
                    }
                }
                // 处理常规字段
                if ($filter['key'] !== 'expire_days') {
                    $builder->where($filter['key'], $filter['condition'], $filter['value']);
                }
            }
        }
    }
    public function fetch(UserFetch $request)
    {
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        
        // 构建基础查询
        $userModel = User::select(
            DB::raw('*'),
            DB::raw('(u+d) as total_used')
        )->orderBy($sort, $sortType);
        
        // 处理 plan_id 参数，支持单个ID和数组
        if ($request->has('plan_id')) {
            $planIds = is_array($request->input('plan_id')) 
                ? $request->input('plan_id') 
                : [$request->input('plan_id')];
                
            // 过滤掉空值和非数字
            $planIds = array_filter($planIds, function($id) {
                return !is_null($id) && is_numeric($id);
            });
            
            if (!empty($planIds)) {
                $userModel->whereIn('plan_id', $planIds);
            }
        }
        
        // 仅当传入过期天数时添加过期时间筛选条件
        if ($request->has('expire_days') && $request->input('expire_days') !== null) {
            $expireDays = (int)$request->input('expire_days');
            $targetTimestamp = strtotime("+{$expireDays} days");
            $userModel->where('expired_at', '<=', $targetTimestamp)
                     ->where('expired_at', '>', 0); // 排除无限期用户
        }

        // 应用其他过滤条件
        $this->filter($request, $userModel);
        
        $total = $userModel->count();
        $res = $userModel->forPage($current, $pageSize)
            ->get();
            
        // 关联套餐信息
        $plan = Plan::get();
        for ($i = 0; $i < count($res); $i++) {
            for ($k = 0; $k < count($plan); $k++) {
                if ($plan[$k]['id'] == $res[$i]['plan_id']) {
                    $res[$i]['plan_name'] = $plan[$k]['name'];
                }
            }
            $res[$i]['subscribe_url'] = Helper::getSubscribeUrl('/api/v1/client/subscribe?token=' . $res[$i]['token']);
        }

        return response([
            'data' => $res,
            'total' => $total
        ]);
    }

    public function getUserInfoById(Request $request)
    {
        if (empty($request->input('id'))) {
            abort(500, '参数错误');
        }
        $user = User::find($request->input('id'));
        if ($user->invite_user_id) {
            $user['invite_user'] = User::find($user->invite_user_id);
        }
        return response([
            'data' => $user
        ]);
    }

    public function update(UserUpdate $request)
    {
        $params = $request->validated();
        $user = User::find($request->input('id'));
        if (!$user) {
            abort(500, '用户不存在');
        }
        if (User::where('email', $params['email'])->first() && $user->email !== $params['email']) {
            abort(500, '邮箱已被使用');
        }
        if (isset($params['password'])) {
            $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
            $params['password_algo'] = NULL;
        } else {
            unset($params['password']);
        }
        if (isset($params['plan_id'])) {
            $plan = Plan::find($params['plan_id']);
            if (!$plan) {
                abort(500, '订阅计划不存在');
            }
            $params['group_id'] = $plan->group_id;
        }
        if ($request->input('invite_user_email')) {
            $inviteUser = User::where('email', $request->input('invite_user_email'))->first();
            if ($inviteUser) {
                $params['invite_user_id'] = $inviteUser->id;
            }
        } else {
            $params['invite_user_id'] = null;
        }

        if (isset($params['banned']) && (int)$params['banned'] === 1) {
            $authService = new AuthService($user);
            $authService->removeAllSession();
        }

        try {
            $user->update($params);
        } catch (\Exception $e) {
            abort(500, '保存失败');
        }
        return response([
            'data' => true
        ]);
    }

    public function dumpCSV(Request $request)
    {
        $userModel = User::orderBy('id', 'asc');
        $this->filter($request, $userModel);
        $res = $userModel->get();
        $plan = Plan::get();
        for ($i = 0; $i < count($res); $i++) {
            for ($k = 0; $k < count($plan); $k++) {
                if ($plan[$k]['id'] == $res[$i]['plan_id']) {
                    $res[$i]['plan_name'] = $plan[$k]['name'];
                }
            }
        }

        $data = "邮箱,余额,推广佣金,总流量,剩余流量,套餐到期时间,订阅计划,订阅地址\r\n";
        foreach($res as $user) {
            $expireDate = $user['expired_at'] === NULL ? '长期有效' : date('Y-m-d H:i:s', $user['expired_at']);
            $balance = $user['balance'] / 100;
            $commissionBalance = $user['commission_balance'] / 100;
            $transferEnable = $user['transfer_enable'] ? $user['transfer_enable'] / 1073741824 : 0;
            $notUseFlow = (($user['transfer_enable'] - ($user['u'] + $user['d'])) / 1073741824) ?? 0;
            $planName = $user['plan_name'] ?? '无订阅';
            $subscribeUrl = Helper::getSubscribeUrl('/api/v1/client/subscribe?token=' . $user['token']);
            $data .= "{$user['email']},{$balance},{$commissionBalance},{$transferEnable},{$notUseFlow},{$expireDate},{$planName},{$subscribeUrl}\r\n";
        }
        echo "\xEF\xBB\xBF" . $data;
    }

    public function generate(UserGenerate $request)
    {
        if ($request->input('email_prefix')) {
            if ($request->input('plan_id')) {
                $plan = Plan::find($request->input('plan_id'));
                if (!$plan) {
                    abort(500, '订阅计划不存在');
                }
            }
            $user = [
                'email' => $request->input('email_prefix') . '@' . $request->input('email_suffix'),
                'plan_id' => isset($plan->id) ? $plan->id : NULL,
                'group_id' => isset($plan->group_id) ? $plan->group_id : NULL,
                'transfer_enable' => isset($plan->transfer_enable) ? $plan->transfer_enable * 1073741824 : 0,
                'expired_at' => $request->input('expired_at') ?? NULL,
                'uuid' => Helper::guid(true),
                'token' => Helper::guid()
            ];
            if (User::where('email', $user['email'])->first()) {
                abort(500, '邮箱已存在于系统中');
            }
            $user['password'] = password_hash($request->input('password') ?? $user['email'], PASSWORD_DEFAULT);
            if (!User::create($user)) {
                abort(500, '生成失败');
            }
            return response([
                'data' => true
            ]);
        }
        if ($request->input('generate_count')) {
            $this->multiGenerate($request);
        }
    }

    private function multiGenerate(Request $request)
    {
        if ($request->input('plan_id')) {
            $plan = Plan::find($request->input('plan_id'));
            if (!$plan) {
                abort(500, '订阅计划不存在');
            }
        }
        $users = [];
        for ($i = 0;$i < $request->input('generate_count');$i++) {
            $user = [
                'email' => Helper::randomChar(6) . '@' . $request->input('email_suffix'),
                'plan_id' => isset($plan->id) ? $plan->id : NULL,
                'group_id' => isset($plan->group_id) ? $plan->group_id : NULL,
                'transfer_enable' => isset($plan->transfer_enable) ? $plan->transfer_enable * 1073741824 : 0,
                'expired_at' => $request->input('expired_at') ?? NULL,
                'uuid' => Helper::guid(true),
                'token' => Helper::guid(),
                'created_at' => time(),
                'updated_at' => time()
            ];
            $user['password'] = password_hash($request->input('password') ?? $user['email'], PASSWORD_DEFAULT);
            array_push($users, $user);
        }
        DB::beginTransaction();
        if (!User::insert($users)) {
            DB::rollBack();
            abort(500, '生成失败');
        }
        DB::commit();
        $data = "账号,密码,过期时间,UUID,创建时间,订阅地址\r\n";
        foreach($users as $user) {
            $expireDate = $user['expired_at'] === NULL ? '长期有效' : date('Y-m-d H:i:s', $user['expired_at']);
            $createDate = date('Y-m-d H:i:s', $user['created_at']);
            $password = $request->input('password') ?? $user['email'];
            $subscribeUrl = Helper::getSubscribeUrl('/api/v1/client/subscribe?token=' . $user['token']);
            $data .= "{$user['email']},{$password},{$expireDate},{$user['uuid']},{$createDate},{$subscribeUrl}\r\n";
        }
        echo $data;
    }

    public function sendMail(UserSendMail $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        $users = $builder->get();
        foreach ($users as $user) {
            SendEmailJob::dispatch([
                'email' => $user->email,
                'subject' => $request->input('subject'),
                'template_name' => 'notify',
                'template_value' => [
                    'name' => config('v2board.app_name', 'V2Board'),
                    'url' => config('v2board.app_url'),
                    'content' => $request->input('content')
                ]
            ],
            'send_email_mass');
        }

        return response([
            'data' => true
        ]);
    }

    public function ban(Request $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        try {
            $builder->update([
                'banned' => 1
            ]);
        } catch (\Exception $e) {
            abort(500, '处理失败');
        }

        return response([
            'data' => true
        ]);
    }

    /**
     * 批量删除用户
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function batchDelete(Request $request)
    {   
        // 验证请求参数，支持单个ID或数组
        $request->validate([
            'ids' => 'required'
        ]);

        // 处理输入参数，确保为数组格式
        $userIds = is_array($request->input('ids')) ? $request->input('ids') : [$request->input('ids')];
        try {
            DB::beginTransaction();
            
            // 查找要删除的用户并检查是否到期
            $users = User::whereIn('id', $userIds)
                ->where(function($query) {
                    $query->where('expired_at', '<=', time())
                        ->orWhere('expired_at', 0);
                })
                ->get();
            if ($users->isEmpty()) {
                throw new \Exception('未找到符合删除条件的用户（用户不存在或未到期）');
            }

            // 记录要删除的用户ID
            $validUserIds = $users->pluck('id')->toArray();
            
            // 同步用户数据到 UserDel 表，保持原有的时间戳
            foreach ($users as $user) {
                $userData = $user->getAttributes(); // 获取所有原始属性
                unset($userData['id']); // 移除 id 字段以允许自增
                // 添加删除相关信息
                $userData = array_merge($userData, [
                    'deleted_at' => time(),
                    'delete_reason' => '批量删除-账户到期',
                    // 保持原有的创建和更新时间
                    'created_at' => $user->created_at,
                    'updated_at' => time()
                ]);

                // 插入到 UserDel 表
                UserDel::create($userData);
            }
            
            // 删除原用户数据
            $deletedCount = User::whereIn('id', $validUserIds)->delete();
            
            DB::commit();
            
            // 记录操作日志
            \Log::info('批量删除用户成功', [
                'requested_ids' => $userIds,
                'deleted_ids' => $validUserIds,
                'deleted_count' => $deletedCount
            ]);
            
            return response([
                'data' => [
                    'deleted_count' => $deletedCount,
                    'success' => true,
                    'message' => sprintf(
                        "成功删除 %d 个用户%s",
                        $deletedCount,
                        (count($userIds) - $deletedCount > 0) ? 
                            sprintf("，有 %d 个用户不符合删除条件", count($userIds) - $deletedCount) : 
                            ""
                    )
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('批量删除用户失败:', [
                'message' => $e->getMessage(),
                'user_ids' => $userIds,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response([
                'data' => [
                    'success' => false,
                    'message' => '删除失败：' . $e->getMessage()
                ]
            ], 400);
        }

    }

    /*
    * 续费新购买获取单个用户数据
    * @param Request $request
    */
    public function getRenewalNewPurchase(Request $request) 
    {
        // 验证请求参数
        $request->validate([
            'user_id' => 'integer|nullable',
            'email' => 'email|nullable'
        ], [
            'user_id.integer' => '用户ID必须为数字',
            'email.email' => '邮箱格式不正确'
        ]);

        // 至少需要提供一个查询条件
        if (!$request->has('user_id') && !$request->has('email')) {
            return response([
                'message' => '请提供用户ID或邮箱进行查询'
            ], 400);
        }

        // 查找用户
        $user = null;
        if ($request->has('user_id')) {
            $user = User::find($request->input('user_id'));
        } else if ($request->has('email')) {
            $user = User::where('email', $request->input('email'))->first();
        }

        if (!$user) {
            return response([
                'message' => '用户不存在'
            ], 404);
        }

        $userId = $user->id;
        
        // 定义时间周期映射
        $periods = [
            'month_1' => ['period' => 'month_price', 'name' => '月付'],
            'quarter' => ['period' => 'quarter_price', 'name' => '季付'],
            'half_year' => ['period' => 'half_year_price', 'name' => '半年付'],
            'year' => ['period' => 'year_price', 'name' => '年付'],
            'onetime' => ['period' => 'onetime_price', 'name' => '一次性'],
            'reset_price' => ['period' => 'reset_price', 'name' => '重置流量'],
        ];

        try {
            $result = [];
            
            // 遍历每个时间周期
            foreach ($periods as $key => $periodInfo) {
                // 查询新购订单数量
                $newPurchaseCount = \App\Models\Order::where([
                    'invite_user_id' => $userId,
                    'type' => 1, // 新购
                    'status' => 3, // 已完成
                    'period' => $periodInfo['period']
                ])->count();

                // 查询续费订单数量
                $renewalCount = \App\Models\Order::where([
                    'invite_user_id' => $userId,
                    'type' => 2, // 续费
                    'status' => 3, // 已完成
                    'period' => $periodInfo['period']
                ])->count();

                // 获取订单金额统计
                $newPurchaseAmount = \App\Models\Order::where([
                    'invite_user_id' => $userId,
                    'type' => 1,
                    'status' => 3,
                    'period' => $periodInfo['period']
                ])->sum('total_amount');

                $renewalAmount = \App\Models\Order::where([
                    'invite_user_id' => $userId,
                    'type' => 2,
                    'status' => 3,
                    'period' => $periodInfo['period']
                ])->sum('total_amount');

                // 获取佣金统计
                $newPurchaseCommission = \App\Models\Order::where([
                    'invite_user_id' => $userId,
                    'type' => 1,
                    'status' => 3,
                    'commission_status' => 2, // 有效佣金
                    'period' => $periodInfo['period']
                ])->sum('actual_commission_balance');

                $renewalCommission = \App\Models\Order::where([
                    'invite_user_id' => $userId,
                    'type' => 2,
                    'status' => 3,
                    'commission_status' => 2,
                    'period' => $periodInfo['period']
                ])->sum('actual_commission_balance');

                $result[$key] = [
                    'period_name' => $periodInfo['name'],
                    'new_purchase' => [
                        'count' => $newPurchaseCount,
                        'amount' => $newPurchaseAmount / 100, // 转换为元
                        'commission' => $newPurchaseCommission / 100
                    ],
                    'renewal' => [
                        'count' => $renewalCount,
                        'amount' => $renewalAmount / 100,
                        'commission' => $renewalCommission / 100
                    ],
                    'total' => [
                        'count' => $newPurchaseCount + $renewalCount,
                        'amount' => ($newPurchaseAmount + $renewalAmount) / 100,
                        'commission' => ($newPurchaseCommission + $renewalCommission) / 100
                    ]
                ];
            }

            // 修改计算总计数据的部分
            $totals = [
                'new_purchase' => [
                    'count' => 0,
                    'amount' => 0,
                    'commission' => 0
                ],
                'renewal' => [
                    'count' => 0,
                    'amount' => 0,
                    'commission' => 0
                ]
            ];

            // 正确计算总计
            foreach ($result as $period) {
                $totals['new_purchase']['count'] += $period['new_purchase']['count'];
                $totals['new_purchase']['amount'] += $period['new_purchase']['amount'];
                $totals['new_purchase']['commission'] += $period['new_purchase']['commission'];
                
                $totals['renewal']['count'] += $period['renewal']['count'];
                $totals['renewal']['amount'] += $period['renewal']['amount'];
                $totals['renewal']['commission'] += $period['renewal']['commission'];
            }

            // 获取用户基本信息
            $user = \App\Models\User::find($userId);
            
            return response([
                'data' => [
                    'user_info' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'commission_rate' => $user->commission_rate,
                        'commission_balance' => $user->commission_balance / 100
                    ],
                    'statistics' => $result,
                    'totals' => $totals
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('获取用户新购续费统计失败:', [
                'user_id' => $userId,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response([
                'message' => '获取统计数据失败',
                'error'=> $e->getMessage()
            ], 500);
        }
    }

}
