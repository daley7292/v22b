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

    public function save(Request $request)
    {
        // 1. 验证请求参数
        $validated = $request->validate([
            'id' => 'nullable|integer',  // id可选
            'name' => 'required|string',
            'plan_id' => 'required|integer',
            'duration_unit' => 'required|string',
            'duration_value' => 'required|integer',
            'is_invitation' => 'required|integer',
            'email' => 'required|string|email',
            'ordinal_number' => 'required|integer',
            'end_at' => 'required|integer'
        ], [
            'id.integer' => 'ID必须为整数',
            'name.required' => '名称不能为空',
            'name.string' => '名称必须为字符串',
            'plan_id.required' => '套餐ID不能为空',
            'plan_id.integer' => '套餐ID必须为整数',
            'duration_unit.required' => '时间单位不能为空',
            'duration_unit.string' => '时间单位必须为字符串',
            'duration_value.required' => '时间值不能为空',
            'duration_value.integer' => '时间值必须为整数',
            'is_invitation.required' => '邀请开关不能为空',
            'is_invitation.integer' => '邀请开关必须为整数',
            'email.required' => '邮箱不能为空',
            'email.string' => '邮箱必须为字符串',
            'email.email' => '邮箱格式不正确',
            'ordinal_number.required' => '序号不能为空',
            'ordinal_number.integer' => '序号必须为整数',
            'end_at.required' => '结束时间不能为空',
            'end_at.integer' => '结束时间必须为时间戳'
        ]);

        try {
            // 2. 准备数据
            $data = array_merge($validated, [
                'updated_at' => time()
            ]);

            // 3. 查找并处理数据
            $convert = null;
            if (isset($validated['id'])) {
                $convert = Convert::find($validated['id']);
                if (!$convert) {
                    return response([
                        'message' => '未找到ID为 ' . $validated['id'] . ' 的数据记录'
                    ], 404);
                }
            }

            // 4. 更新或创建记录
            if ($convert) {
                $convert->update($data);
                $message = '更新成功';
            } else {
                $data['created_at'] = time();
                Convert::create($data);
                $message = '创建成功';
            }

            return response([
                'data' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
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
}
