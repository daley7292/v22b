<?php

namespace App\Http\Controllers\Client\Protocols;

use App\Utils\Helper;

class General
{
    public $flag = 'general';
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $uri = '';

        // 获取所有规则并按sort升序排序
        $rules = \App\Models\ServerRule::orderBy('sort', 'ASC')
            ->orderBy('id', 'DESC')
            ->get();
        
        // 获取请求的 User-Agent 并转小写处理
        $userAgent = strtolower(request()->header('User-Agent') ?? '');

        // 添加调试日志
        \Log::info('UA Debug:', [
            'user_agent' => $userAgent,
            'rules_count' => $rules->count()
        ]);

        foreach ($servers as &$item) {
            if (isset($item['group_id'])) {
                // 优化规则匹配逻辑
                $matchedRule = $rules->first(function($rule) use ($userAgent, $item) {
                    // 如果规则无效则跳过
                    if (empty($rule->ua) || empty($rule->server_arr)) {
                        return false;
                    }

                    // UA 匹配检查（不区分大小写）
                    $uaMatch = stripos($userAgent, $rule->ua) !== false;
                    
                    // 分组匹配检查
                    $ruleGroupIds = array_map('trim', explode(',', $rule->server_arr));
                    $groupMatch = in_array((string)$item['group_id'], $ruleGroupIds);

                    // 记录匹配过程
                    \Log::debug('Rule matching:', [
                        'rule_id' => $rule->id,
                        'ua_pattern' => $rule->ua,
                        'current_group' => $item['group_id'],
                        'rule_groups' => $rule->server_arr,
                        'ua_match' => $uaMatch,
                        'group_match' => $groupMatch
                    ]);

                    return $uaMatch && $groupMatch;
                });

                // 应用匹配规则
                if ($matchedRule) {
                    \Log::info('Applied rule:', [
                        'rule_id' => $matchedRule->id,
                        'original_host' => $item['host'],
                        'new_host' => $matchedRule->domain,
                        'new_port' => $matchedRule->port
                    ]);

                    $item['host'] = $matchedRule->domain;
                    if (!is_null($matchedRule->port)) {
                        $item['port'] = $matchedRule->port;
                    }
                }
            }

            // 根据类型构建 URI
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($user['uuid'], $item);
            }
            if ($item['type'] === 'shadowsocks') {
                $uri .= self::buildShadowsocks($user['uuid'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($user['uuid'], $item);
            }
        }
        
        return base64_encode($uri);
    }

    public static function buildShadowsocks($password, $server)
    {
        if ($server['cipher'] === '2022-blake3-aes-128-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 16);
            $userKey = Helper::uuidToBase64($password, 16);
            $password = "{$serverKey}:{$userKey}";
        }
        if ($server['cipher'] === '2022-blake3-aes-256-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 32);
            $userKey = Helper::uuidToBase64($password, 32);
            $password = "{$serverKey}:{$userKey}";
        }
        $name = rawurlencode($server['name']);
        $str = str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode("{$server['cipher']}:{$password}")
        );
        return "ss://{$str}@{$server['host']}:{$server['port']}#{$name}\r\n";
    }

    public static function buildVmess($uuid, $server)
    {
        $config = [
            "v" => "2",
            "ps" => $server['name'],
            "add" => $server['host'],
            "port" => (string)$server['port'],
            "id" => $uuid,
            "aid" => '0',
            "net" => $server['network'],
            "type" => "none",
            "host" => "",
            "path" => "",
            "tls" => $server['tls'] ? "tls" : "",
        ];
        if ($server['tls']) {
            if ($server['tlsSettings']) {
                $tlsSettings = $server['tlsSettings'];
                if (isset($tlsSettings['serverName']) && !empty($tlsSettings['serverName']))
                    $config['sni'] = $tlsSettings['serverName'];
            }
        }
        if ((string)$server['network'] === 'tcp') {
            $tcpSettings = $server['networkSettings'];
            if (isset($tcpSettings['header']['type'])) $config['type'] = $tcpSettings['header']['type'];
            if (isset($tcpSettings['header']['request']['path'][0])) $config['path'] = $tcpSettings['header']['request']['path'][0];
        }
        if ((string)$server['network'] === 'ws') {
            $wsSettings = $server['networkSettings'];
            if (isset($wsSettings['path'])) $config['path'] = $wsSettings['path'];
            if (isset($wsSettings['headers']['Host'])) $config['host'] = $wsSettings['headers']['Host'];
        }
        if ((string)$server['network'] === 'grpc') {
            $grpcSettings = $server['networkSettings'];
            if (isset($grpcSettings['serviceName'])) $config['path'] = $grpcSettings['serviceName'];
        }
        return "vmess://" . base64_encode(json_encode($config)) . "\r\n";
    }

    public static function buildTrojan($password, $server)
    {
        $name = rawurlencode($server['name']);
        $query = http_build_query([
            'allowInsecure' => $server['allow_insecure'],
            'peer' => $server['server_name'],
            'sni' => $server['server_name']
        ]);
        $uri = "trojan://{$password}@{$server['host']}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";
        return $uri;
    }

}
