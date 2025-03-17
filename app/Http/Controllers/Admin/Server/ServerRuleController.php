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
        $routes = ServerRule::get();
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
            'server_arr' => 'required'
        ], [
            'name.required' => '规则名称不能为空',
            'domain.required' => '域名不能为空',
            'port.required' => '端口不能为空',
            'server_arr.required' => '多选服务器分组不能为空'
        ]);

        if ($request->input('id')) {
            try {
                $route = ServerRule::find($request->input('id'));
                $route->update($params);
                return [
                    'data' => true
                ];
            } catch (\Exception $e) {
                abort(500, '保存失败');
            }
        }
        if (!ServerRule::create($params)) abort(500, '创建失败');
        return [
            'data' => true
        ];
    }
}
