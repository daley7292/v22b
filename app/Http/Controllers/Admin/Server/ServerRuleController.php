<?php

namespace App\Http\Controllers\Admin\Server;

use App\Services\ServerService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\ServerRule;
class ServerRuleController extends Controller
{
    public function fetch(Request $request)
    {
        $routes = ServerRule::all();
        return [
            'data' => $routes
        ];
    }

    public function save(Request $request)
    {
        $params = $request->validate([
            'name' => 'required',                   //规则名称
            'domain' => 'required',                 //替换域名
            'port' => 'required',                   //端口
            'server_arr' => 'required'              //服务器分组ID，逗号分隔
        ], [
            'name.required' => '规则名称不能为空',
            'domain.required' => '域名不能为空',
            'port.required' => '端口不能为空',
            'server_arr.required' => '多选服务器分组不能为空'
        ]);

        try {
            // 将传入的服务器ID字符串转为数组
            $newServerIds = explode(',', $params['server_arr']);
            
            // 查询所有现有规则
            $existingRules = ServerRule::all();
            
            // 检查新服务器ID是否已经在其他规则中存在
            foreach ($existingRules as $rule) {
                // 跳过当前正在编辑的规则
                if ($request->input('id') && $rule->id == $request->input('id')) {
                    continue;
                }
                
                // 将已存在规则的服务器ID字符串转为数组
                $existingServerIds = explode(',', $rule->server_arr);
                
                // 检查是否有重复的服务器ID
                $duplicateServers = array_intersect($newServerIds, $existingServerIds);
                if (!empty($duplicateServers)) {
                    return response([
                        'data' => false,
                        'message' => sprintf(
                            '服务器分组 [%s] 已被规则 [%s] 绑定',
                            implode(',', $duplicateServers),
                            $rule->name
                        )
                    ], 400);
                }
            }

            // 更新操作
            if ($request->input('id')) {
                $route = ServerRule::find($request->input('id'));
                $route->update($params);
                return [
                    'data' => true,
                    'message' => '更新成功'
                ];
            }

            // 创建操作
            if (!ServerRule::create($params)) {
                throw new \Exception('创建失败');
            }

            return [
                'data' => true,
                'message' => '创建成功'
            ];

        } catch (\Exception $e) {
            \Log::error('保存服务器规则失败:', [
                'message' => $e->getMessage(),
                'params' => $params
            ]);
            
            return response([
                'data' => false,
                'message' => '操作失败：' . $e->getMessage()
            ], 500);
        }
    }

    public function del(Request $request){

        if ($request->input('id')) {
            if (!ServerRule::del($request->input('id'))) abort(500, '删除失败');
            return [
                'data' => true
            ];
        }
    }
}
