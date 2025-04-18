<?php

namespace App\Http\Controllers\Server;

use App\Models\Plan;
use App\Models\User;
use App\Services\ServerService;
use App\Services\StatisticalService;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ServerShadowsocks;
use App\Models\ServerVmess;
use App\Models\ServerTrojan;
use Illuminate\Support\Facades\Cache;

class UniProxyController extends Controller
{
    private $nodeType;
    private $nodeInfo;
    private $nodeId;
    private $serverService;

    public function __construct(Request $request)
    {
        $token = $request->input('token');
        if (empty($token)) {
            abort(500, 'token is null');
        }
        if ($token !== config('v2board.server_token')) {
            abort(500, 'token is error');
        }
        $this->nodeType = $request->input('node_type');
        if ($this->nodeType === 'v2ray') $this->nodeType = 'vmess';
        $this->nodeId = $request->input('node_id');
        $this->serverService = new ServerService();
        $this->nodeInfo = $this->serverService->getServer($this->nodeId, $this->nodeType);
        if (!$this->nodeInfo) abort(500, 'server is not exist');
    }

    // 后端获取用户
    public function user(Request $request)
    {
        ini_set('memory_limit', -1);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_LAST_CHECK_AT', $this->nodeInfo->id), time(), 3600);
        $users = $this->serverService->getAvailableUsers($this->nodeInfo->group_id);
        $users = $users->toArray();

        $response['users'] = $users;
        // 在这里获取用户信息的时候通过User拿到当前计划，然后通过计划下发限制
        $usersArray=$users;
        $modifiedUsers = [];
        foreach ($usersArray as $user_info) {
            // 使用 User::find($user['id']) 获取用户对象
            $userObject = User::find($user_info['id']);

            if ($userObject['plan_id']) {
                // 获取 block_ipv4_cont 和 block_plant_cont 字段的值
                $plan_data=Plan::find($userObject['plan_id']);
                $blockIpv4Cont = $plan_data->block_ipv4_cont;
                $blockPlantCont = $plan_data->block_plant_cont;
                // 将原数组中的数据与新获取的字段合并
                $modifiedUser = [
                    'id' => $user_info['id'],
                    'uuid' => $user_info['uuid'],
                    'speed_limit' => $user_info['speed_limit'],
                    'ip_limit' => $user_info['ip_limit'],
                    'block_ipv4_cont' => $blockIpv4Cont,
                    'block_plant_cont' => $blockPlantCont
                ];

                // 将修改后的用户数据添加到新数组中
                $modifiedUsers[] = $modifiedUser;
            } else {
                // 如果用户不存在，可以选择跳过或记录错误
                $modifiedUsers[] = $user_info; // 这里直接保留原数组数据，或添加错误处理逻辑
            }
        }
        $response['users'] =$modifiedUsers;
        //代码截至与此---------------------------------------------------
        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match'), $eTag) !== false ) {
            abort(304);
        }

        return response($response)->header('ETag', "\"{$eTag}\"");
    }

    // 后端提交数据
    public function push(Request $request)
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_ONLINE_USER', $this->nodeInfo->id), count($data), 3600);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_LAST_PUSH_AT', $this->nodeInfo->id), time(), 3600);
        $userService = new UserService();
        $userService->trafficFetch($this->nodeInfo->toArray(), $this->nodeType, $data);

        return response([
            'data' => true
        ]);
    }

    // 后端获取配置
    public function config(Request $request)
    {

        switch ($this->nodeType) {
            case 'shadowsocks':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'cipher' => $this->nodeInfo->cipher,
                    'obfs' => $this->nodeInfo->obfs,
                    'obfs_settings' => $this->nodeInfo->obfs_settings
                ];

                if ($this->nodeInfo->cipher === '2022-blake3-aes-128-gcm') {
                    $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 16);
                }
                if ($this->nodeInfo->cipher === '2022-blake3-aes-256-gcm') {
                    $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 32);
                }
                break;
            case 'vmess':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'network' => $this->nodeInfo->network,
                    'networkSettings' => $this->nodeInfo->networkSettings,
                    'tls' => $this->nodeInfo->tls
                ];
                break;
            case 'trojan':
                $response = [
                    'host' => $this->nodeInfo->host,
                    'server_port' => $this->nodeInfo->server_port,
                    'server_name' => $this->nodeInfo->server_name,
                ];
                break;
            case 'hysteria':
                $response = [
                    'host' => $this->nodeInfo->host,
                    'server_port' => $this->nodeInfo->server_port,
                    'server_name' => $this->nodeInfo->server_name,
                    'up_mbps' => $this->nodeInfo->up_mbps,
                    'down_mbps' => $this->nodeInfo->down_mbps,
                    'obfs' => Helper::getServerKey($this->nodeInfo->created_at, 16)
                ];
                break;
        }
        $response['base_config'] = [
            'push_interval' => (int)config('v2board.server_push_interval', 60),
            'pull_interval' => (int)config('v2board.server_pull_interval', 60)
        ];
        if ($this->nodeInfo['route_id']) {
            $response['routes'] = $this->serverService->getRoutes($this->nodeInfo['route_id']);
        }
        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match'), $eTag) !== false ) {
            abort(304);
        }

        return response($response)->header('ETag', "\"{$eTag}\"");
    }
}
