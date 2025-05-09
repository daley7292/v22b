<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Protocols\General;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\ServerService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\UserService;

class ClientController extends Controller
{
    public function subscribe(Request $request)
    {

        $flag = $request->input('flag')
            ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $flag = strtolower($flag);
        $user = $request->user;
        $platform = $request->input('platform') ?? ($request->input('p') ?? '' );
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $servers = $this->filterServers($servers, $request);
             
            /*
            if($platform===''){
                $servers = array_filter($servers, function($server) {
                    if (isset($server['tags']) && is_array($server['tags'])) {
                        return in_array('WEB', $server['tags']);
                    }
                    return false;
                });
            }else{
                $servers = array_filter($servers, function($server) use ($platform) {
                    if (isset($server['tags']) && is_array($server['tags'])) {
                        return in_array($platform, $server['tags']);
                    }
                    return false;
                });
            }
            */
        } else {
            $subsDomain = $_SERVER['HTTP_HOST'];
            $servers = [
                [
                    'type' => 'shadowsocks',
                    'port' => 443,
                    'host' => 'www.google.com',
                    'cipher' => 'aes-128-gcm',
                    'name' => '您的服务已到期',
                ],
                [
                    'type' => 'shadowsocks',
                    'port' => 443,
                    'host' => 'www.google.com',
                    'cipher' => 'aes-128-gcm',
                    'name' => '请登录'. $subsDomain.' 续费',
                ],
            ];
        }
        $this->setSubscribeInfoToServers($servers, $user);
        if ($flag) {
            foreach (array_reverse(glob(app_path('Http//Controllers//Client//Protocols') . '/*.php')) as $file) {
                $file = 'App\\Http\\Controllers\\Client\\Protocols\\' . basename($file, '.php');
                $class = new $file($user, $servers);
                if (strpos($flag, $class->flag) !== false) {
                    die($class->handle());
                }
            }
        }
        $class = new General($user, $servers);
        die($class->handle());
    }

    

    private function setSubscribeInfoToServers(&$servers, $user)
    {
        if (!isset($servers[0])) return;
        if (!(int)config('v2board.show_info_to_server_enable', 0)) return;
        $url = config('v2board.app_url');
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : '长期有效';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        if ($resetDay) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "距离下次重置剩余：{$resetDay} 天",
            ]));
        }
        array_unshift($servers, array_merge($servers[0], [
            'name' => "官网:{$url}",
        ]));
        array_unshift($servers, array_merge($servers[0], [
            'name' => "时间不得相差超过15s",
        ]));
        array_unshift($servers, array_merge($servers[0], [
            'name' => "到期:{$expiredDate},剩余:{$remainingTraffic}",
        ]));
    }

private function filterServers(&$servers, Request $request)
{
    // 获取输入
    $include = $request->input('include');
    $exclude = $request->input('exclude');

    // 将输入字符串转换为数组
    $includeArray = preg_split('/[,|]/', $include, -1, PREG_SPLIT_NO_EMPTY);
    $excludeArray = preg_split('/[,|]/', $exclude, -1, PREG_SPLIT_NO_EMPTY);

    // 过滤 servers 数组
    $servers = array_filter($servers, function($item) use ($includeArray, $excludeArray) {
        // 检查是否包含任何 include 词
        $includeMatch = empty($includeArray) || array_reduce($includeArray, function($carry, $word) use ($item) {
            return $carry || (stripos($item['name'], $word) !== false);
        }, false);

        // 检查是否不包含所有 exclude 词
        $excludeMatch = empty($excludeArray) || array_reduce($excludeArray, function($carry, $word) use ($item) {
            return $carry && (stripos($item['name'], $word) === false);
        }, true);

        return $includeMatch && $excludeMatch;
    });
    return $servers;
}


public function getuuidSubscribe(Request $request)  {
    $user = User::where([
        'email' => $request->query('email'),
        'uuid' => $request->query('uuid')
    ])->first();

    if (!$user) {
        return response()->json([
            'message' => '用户不存在'
        ], 404);
    }
    $user = User::where('id', $user->id)
    ->select([
        'plan_id',
        'token',
        'expired_at',
        'u',
        'd',
        'transfer_enable',
        'email',
        'uuid'
    ])
    ->first();
    if (!$user) {
        abort(500, __('The user does not exist'));
    }
    if ($user->plan_id) {
        $user['plan'] = Plan::find($user->plan_id);
        if (!$user['plan']) {
            abort(500, __('Subscription plan does not exist'));
        }
    }
    $user['subscribe_url'] = Helper::getSubscribeUrl("/api/v1/client/subscribe?token={$user['token']}");
    $userService = new UserService();
    $user['reset_day'] = $userService->getResetDay($user);
    return response([
        'data' => $user
    ]);

}


}
