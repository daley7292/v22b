<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ConfigSave;
use App\Jobs\SendEmailJob;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use App\Utils\Dict;
use App\Http\Controllers\Controller;
use App\Models\Convert;

class ConvertController extends Controller
{

    public function fetch(Request $request)
    {

        try {
            // 按创建时间倒序获取所有数据
            $data = Convert::orderBy('created_at', 'DESC')->get();
            
            return response([
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Log::error('获取Convert数据失败:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response([
                'message' => '获取数据失败'
            ], 500);
        }
    }

    /**
     * 生成唯一的兑换码
     * @return string
     */
    private function generateUniqueRedeemCode()
    {
        do {
            // 使用不易混淆的字符
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';  // 排除了 iIl1o0O
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            // 检查是否已存在
            $exists = Convert::where('redeem_code', $code)->exists();
        } while ($exists);

        return $code;
    }

    public function save(Request $request)
    {
        // 1. 验证请求参数
        $validated = $request->validate([
            'id' => 'nullable|integer',  // id可选
            'name' => 'required|string|max:255',
            'plan_id' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:day,month,year,quarter,half_year,onetime',
            'duration_value' => 'required|integer|min:1',
            'is_invitation' => 'required|integer|in:0,1',
            'email' => 'nullable|string|email|max:255',
            'ordinal_number' => 'required|integer|min:-1', // 允许-1(已用尽)、0(无限制)和正整数
            'end_at' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value < time()) {
                        $fail('结束时间不能小于当前时间');
                    }
                }
            ],
            // 新增count参数，可选，默认为1
            'count' => 'nullable|integer|min:1|max:100'
        ], [
            'id.integer' => 'ID必须为整数',
            'name.required' => '名称不能为空',
            'name.string' => '名称必须为字符串',
            'name.max' => '名称最大长度为255个字符',
            'plan_id.required' => '套餐ID不能为空',
            'plan_id.integer' => '套餐ID必须为整数',
            'plan_id.min' => '套餐ID必须大于0',
            'duration_unit.required' => '时间单位不能为空',
            'duration_unit.string' => '时间单位必须为字符串',
            'duration_unit.in' => '时间单位只能是 day/month/year/quarter/half_year/onetime',
            'duration_value.required' => '时间值不能为空',
            'duration_value.integer' => '时间值必须为整数',
            'duration_value.min' => '时间值必须大于0',
            'is_invitation.required' => '邀请开关不能为空',
            'is_invitation.integer' => '邀请开关必须为整数',
            'is_invitation.in' => '邀请开关只能是0或1',
            'email.string' => '邮箱必须为字符串',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱最大长度为255个字符',
            'ordinal_number.required' => '兑换次数不能为空',
            'ordinal_number.integer' => '兑换次数必须为整数',
            'ordinal_number.min' => '兑换次数不能小于-1',
            'end_at.required' => '结束时间不能为空',
            'end_at.integer' => '结束时间必须为时间戳格式',
            'count.integer' => '生成数量必须为整数',
            'count.min' => '生成数量必须大于0',
            'count.max' => '单次最多生成100个兑换码'
        ]);

        try {
            // 开始事务
            \DB::beginTransaction();
            
            // 获取当前时间戳
            $now = time();
            
            // 2. 准备数据
            $data = array_merge($validated, [
                'created_at' => $now,
                'updated_at' => $now,
                'end_at' => $validated['end_at']
            ]);

            // 3. 查找并处理数据
            $convert = null;
            
            // 处理更新情况
            if (isset($validated['id'])) {
                $convert = Convert::find($validated['id']);
                if (!$convert) {
                    return response([
                        'message' => '未找到ID为 ' . $validated['id'] . ' 的数据记录'
                    ], 404);
                }
                
                // 更新操作忽略count参数
                // 更新时只更新 updated_at，并保持原有 redeem_code 不变
                $data['updated_at'] = $now;
                unset($data['created_at']);
                unset($data['redeem_code']);
                unset($data['count']);

                // 检查兑换次数修改的合法性
                if ($convert->ordinal_number === -1 && $validated['ordinal_number'] !== -1) {
                    return response([
                        'message' => '已用尽的兑换码不能重新启用'
                    ], 400);
                }
                
                // 更新记录
                $convert->update($data);
                $message = '更新成功';
                $result = [$convert->redeem_code];
            } else {
                // 新建记录
                
                // 确保新建时兑换次数不为-1
                if ($data['ordinal_number'] === -1) {
                    return response([
                        'message' => '新建记录的兑换次数不能为-1'
                    ], 400);
                }
                
                // 获取要生成的数量，默认为1
                $count = isset($validated['count']) ? (int)$validated['count'] : 1;
                
                // 移除count字段，避免写入数据库
                unset($data['count']);
                
                // 存储生成的兑换码
                $redeemCodes = [];
                
                // 循环创建记录
                for ($i = 0; $i < $count; $i++) {
                    // 为每条记录生成唯一的兑换码
                    $data['redeem_code'] = $this->generateUniqueRedeemCode();
                    $redeemCodes[] = $data['redeem_code'];
                    
                    // 创建记录
                    Convert::create($data);
                }
                
                $message = $count > 1 ? "成功创建 {$count} 个兑换码" : '创建成功';
                $result = $redeemCodes;
            }

            \DB::commit();
            
            return response([
                'data' => $result,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            
            \Log::error('保存Convert数据失败:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);

            return response([
                'message' => '保存失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取兑换码关联的订单数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRedeemOrders(Request $request)
    {
        try {
            // 1. 验证请求参数
            $validated = $request->validate([
                'redeem_code' => 'required|string|size:6',
            ], [
                'redeem_code.required' => '兑换码不能为空',
                'redeem_code.string' => '兑换码必须为字符串',
                'redeem_code.size' => '兑换码长度必须为6位'
            ]);

            // 2. 获取订单数据并关联用户信息
            $orders = \App\Models\Order::with(['user' => function($query) {
                    $query->select('id', 'email');
                }])
                ->where('redeem_code', $validated['redeem_code'])
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($order) {
                    return [
                        'order_id' => $order->id,
                        'trade_no' => $order->trade_no,
                        'email' => $order->user->email ?? '未知',
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'created_at' => date('Y-m-d H:i:s', $order->created_at),
                        'updated_at' => date('Y-m-d H:i:s', $order->updated_at)
                    ];
                });

            // 3. 获取兑换码信息
            $convert = Convert::where('redeem_code', $validated['redeem_code'])->first();
            if (!$convert) {
                return response([
                    'message' => '未找到该兑换码记录'
                ], 404);
            }

            return response([
                'data' => [
                    'convert' => [
                        'name' => $convert->name,
                        'email' => $convert->email,
                        'ordinal_number' => $convert->ordinal_number,
                        'end_at' => date('Y-m-d H:i:s', $convert->end_at)
                    ],
                    'orders' => $orders
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('获取兑换码订单数据失败:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'redeem_code' => $request->input('redeem_code')
            ]);

            return response([
                'message' => '获取数据失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除兑换码记录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $id = $request->input('id');
        if (empty($id)) {
            return response([
                'message' => 'ID不能为空'
            ], 400);
        }

        try {
            $convert = Convert::find($id);
            if (!$convert) {
                return response([
                    'message' => '未找到ID为 ' . $id . ' 的数据记录'
                ], 404);
            }
            $convert->delete();

            return response([
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('删除Convert数据失败:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id
            ]);

            return response([
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }
}
