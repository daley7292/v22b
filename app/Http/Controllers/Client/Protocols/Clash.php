<?php

namespace App\Http\Controllers\Client\Protocols;

use App\Utils\Dict;
use phpDocumentor\Reflection\Types\Self_;
use Symfony\Component\Yaml\Yaml;

class Clash
{
    public $flag = 'clash';
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        try {
            $servers = $this->servers;
            $user = $this->user;
            $appName = config('v2board.app_name', 'V2Board');
            header("subscription-userinfo: upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}");
            header('profile-update-interval: 24');
            header("content-disposition:attachment;filename*=UTF-8''".rawurlencode($appName));
            header("profile-web-page-url:" . config('v2board.app_url'));
            $defaultConfig = base_path() . '/resources/rules/default.clash.yaml';
            $customConfig = base_path() . '/resources/rules/custom.clash.yaml';

            // 加载配置
            try {
                if (\File::exists($customConfig)) {
                    $config = Yaml::parseFile($customConfig);
                    var_dump('使用自定义配置文件');
                } else {
                    $config = Yaml::parseFile($defaultConfig);
                    var_dump('使用默认配置文件');
                }
            } catch (\Exception $e) {
                \Log::error('配置文件解析失败', ['error' => $e->getMessage()]);
                $config = [
                    'port' => 7890,
                    'proxies' => [],
                    'proxy-groups' => [],
                    'rules' => []
                ];
            }
            
            // 初始化配置结构
            if (!isset($config['proxies']) || !is_array($config['proxies'])) {
                $config['proxies'] = [];
            }
            
            if (!isset($config['proxy-groups']) || !is_array($config['proxy-groups'])) {
                $config['proxy-groups'] = [];
            }
            
            if (!isset($config['rules']) || !is_array($config['rules'])) {
                $config['rules'] = [];
            }
            
            // 获取规则
            $rules = \App\Models\ServerRule::orderBy('sort', 'ASC')
                    ->orderBy('id', 'DESC')
                    ->get();
           
            // 获取UA
            $userAgent = request()->header('User-Agent') ?? '';
            \Log::info('UA匹配处理', ['user_agent' => $userAgent]);
            
            $proxy = [];
            $proxies = [];

            // 处理服务器列表
            foreach ($servers as &$item) {  // 保持引用符号 &
                /*
                var_dump([
                    'before_match' => [
                        'name' => $item['name'],
                        'group_id' => $item['group_id'] ?? 'none',
                        'host' => $item['host'],
                        'port' => $item['port']
                    ]
                ]);
                */
                // UA规则匹配逻辑
                if (isset($item['group_id'])) {
                    try {
                        // 显示可用的所有规则，便于调试
                        //var_dump(['所有规则' => $rules->toArray()]);
                        
                        $matchedRule = $rules->first(function($rule) use ($userAgent, $item) {
                            if (empty($rule->ua)) {
                                return false;
                            }
                            
                            // 检查 UA 匹配
                            $uaMatch = stripos($userAgent, $rule->ua) !== false;
                            if (!$uaMatch) {
                                return false;
                            }
                            
                            // 修改这段逻辑来处理group_id为数组的情况
                            $ruleGroupIds = array_map('trim', explode(',', $rule->server_arr ?? ''));
                            
                            // 检查group_id是否为数组
                            if (is_array($item['group_id'])) {
                                // 任意一个group_id匹配即可
                                $matched = false;
                                foreach ($item['group_id'] as $gid) {
                                    if (in_array((string)$gid, $ruleGroupIds)) {
                                        $matched = true;
                                        break;
                                    }
                                }
                            } else {
                                // 原来的逻辑，处理group_id为字符串的情况
                                $matched = in_array((string)$item['group_id'], $ruleGroupIds);
                            }
                            /*
                            // 输出详细匹配信息
                            var_dump([
                                'rule_id' => $rule->id,
                                'ua_match' => $uaMatch,
                                'item_group_id' => is_array($item['group_id']) ? implode(',', $item['group_id']) : $item['group_id'],
                                'rule_group_ids' => $ruleGroupIds,
                                'group_match' => $matched
                            ]);
                            */
                            return $matched;
                        });

                        if ($matchedRule) {
                            // 临时备份原始值
                            $oldHost = $item['host'];
                            $oldPort = $item['port'];
                            
                            // 确保替换生效
                            $item['host'] = $matchedRule->domain;
                            if (!is_null($matchedRule->port)) {
                                $item['port'] = $matchedRule->port;
                            }
                            /*
                            var_dump([
                                'match_success' => true,
                                'rule_id' => $matchedRule->id,
                                'old_host' => $oldHost,
                                'new_host' => $item['host'],
                                'old_port' => $oldPort,
                                'new_port' => $item['port']
                            ]);
                            */
                        } else {
                           // var_dump(['match_success' => false]);
                        }
                    } catch (\Exception $e) {
                        //var_dump(['error' => $e->getMessage()]);
                    }
                }
                
                // 输出修改后的节点信息，确认替换是否成功
                /*
                var_dump([
                    'after_match' => [
                        'name' => $item['name'],
                        'host' => $item['host'],
                        'port' => $item['port']
                    ]
                ]);
                */    
                // 这里添加构建代理的代码
                try {
                    if ($item['type'] === 'shadowsocks' 
                        && in_array($item['cipher'] ?? '', [
                            'aes-128-gcm',
                            'aes-192-gcm',
                            'aes-256-gcm',
                            'chacha20-ietf-poly1305'
                        ])
                    ) {
                        $proxy[] = self::buildShadowsocks($user['uuid'], $item);
                        $proxies[] = $item['name'];
                    }
                    
                    if ($item['type'] === 'vmess') {
                        $proxy[] = self::buildVmess($user['uuid'], $item);
                        $proxies[] = $item['name'];
                    }
                    
                    if ($item['type'] === 'trojan') {
                        $proxy[] = self::buildTrojan($user['uuid'], $item);
                        $proxies[] = $item['name'];
                    }
                    /*
                    var_dump([
                        'message' => '节点构建成功',
                        'name' => $item['name'],
                        'type' => $item['type'],
                        'host' => $item['host'],
                        'port' => $item['port']
                    ]);
                    */
                } catch (\Exception $e) {
                    \Log::error('构建节点配置失败', [
                        'name' => $item['name'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }
            // 释放引用
            unset($item);

            // 合并代理
            $config['proxies'] = array_merge($config['proxies'], $proxy);
            /*
            var_dump([
                'message' => '代理配置处理完成',
                'count' => count($proxy)
            ]);
            */
            // 处理代理组
            try {
                foreach ($config['proxy-groups'] as $k => $v) {
                    if (!isset($config['proxy-groups'][$k]['proxies']) || !is_array($config['proxy-groups'][$k]['proxies'])) {
                        $config['proxy-groups'][$k]['proxies'] = [];
                    }
                    
                    $isFilter = false;
                    foreach ($config['proxy-groups'][$k]['proxies'] as $src) {
                        foreach ($proxies as $dst) {
                            if (!$this->isRegex($src)) continue;
                            $isFilter = true;
                            $config['proxy-groups'][$k]['proxies'] = array_values(array_diff($config['proxy-groups'][$k]['proxies'], [$src]));
                            if ($this->isMatch($src, $dst)) {
                                $config['proxy-groups'][$k]['proxies'][] = $dst;
                            }
                        }
                        if ($isFilter) continue;
                    }
                    if ($isFilter) continue;
                    $config['proxy-groups'][$k]['proxies'] = array_merge($config['proxy-groups'][$k]['proxies'], $proxies);
                }

                $config['proxy-groups'] = array_filter($config['proxy-groups'], function($group) {
                    return !empty($group['proxies']);
                });
                $config['proxy-groups'] = array_values($config['proxy-groups']);
            } catch (\Exception $e) {
                \Log::error('处理代理组异常', ['error' => $e->getMessage()]);
            }
            
            // 添加当前域名直连规则
            if (isset($_SERVER['HTTP_HOST'])) {
                array_unshift($config['rules'], "DOMAIN,{$_SERVER['HTTP_HOST']},DIRECT");
            }
            
            // 确保有兜底规则
            if (empty($config['rules'])) {
                $config['rules'][] = "MATCH,DIRECT";
            }

            // 生成YAML
            try {
                /*
                var_dump([
                    'message' => '配置导出前检查',
                    'proxies_count' => count($config['proxies']),
                    'groups_count' => count($config['proxy-groups']),
                    'rules_count' => count($config['rules'])
                ]);
                */    
                $yaml = Yaml::dump($config, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
                $yaml = str_replace('$app_name', config('v2board.app_name', 'V2Board'), $yaml);
                return $yaml;
            } catch (\Exception $e) {
                \Log::error('YAML导出异常', ['error' => $e->getMessage()]);
                throw $e;
            }
            
        } catch (\Exception $e) {
            \Log::error('Clash配置生成失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 返回简化的基础配置
            return Yaml::dump([
                'port' => 7890,
                'proxies' => [],
                'proxy-groups' => [],
                'rules' => ['MATCH,DIRECT']
            ]);
        }
    }

    public static function buildShadowsocks($uuid, $server)
    {
        // 添加调试信息
        var_dump([
            'building_ss' => [
                'name' => $server['name'],
                'host' => $server['host'], 
                'port' => $server['port']
            ]
        ]);
        
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'ss';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['cipher'] = $server['cipher'];
        $array['password'] = $uuid;
        $array['udp'] = true;
        return $array;
    }

    public static function buildVmess($uuid, $server)
    {
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'vmess';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['uuid'] = $uuid;
        $array['alterId'] = 0;
        $array['cipher'] = 'auto';
        $array['udp'] = true;

        if ($server['tls']) {
            $array['tls'] = true;
            if ($server['tlsSettings']) {
                $tlsSettings = $server['tlsSettings'];
                if (isset($tlsSettings['allowInsecure']) && !empty($tlsSettings['allowInsecure']))
                    $array['skip-cert-verify'] = ($tlsSettings['allowInsecure'] ? true : false);
                if (isset($tlsSettings['serverName']) && !empty($tlsSettings['serverName']))
                    $array['servername'] = $tlsSettings['serverName'];
            }
        }
        if ($server['network'] === 'tcp') {
            $tcpSettings = $server['networkSettings'];
            if (isset($tcpSettings['header']['type'])) $array['network'] = $tcpSettings['header']['type'];
            if (isset($tcpSettings['header']['request']['path'][0])) $array['http-opts']['path'] = $tcpSettings['header']['request']['path'][0];
        }
        if ($server['network'] === 'ws') {
            $array['network'] = 'ws';
            if ($server['networkSettings']) {
                $wsSettings = $server['networkSettings'];
                $array['ws-opts'] = [];
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    $array['ws-opts']['path'] = $wsSettings['path'];
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    $array['ws-opts']['headers'] = ['Host' => $wsSettings['headers']['Host']];
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    $array['ws-path'] = $wsSettings['path'];
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    $array['ws-headers'] = ['Host' => $wsSettings['headers']['Host']];
            }
        }
        if ($server['network'] === 'grpc') {
            $array['network'] = 'grpc';
            if ($server['networkSettings']) {
                $grpcSettings = $server['networkSettings'];
                $array['grpc-opts'] = [];
                if (isset($grpcSettings['serviceName'])) $array['grpc-opts']['grpc-service-name'] = $grpcSettings['serviceName'];
            }
        }

        return $array;
    }

    public static function buildTrojan($password, $server)
    {
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'trojan';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['password'] = $password;
        $array['udp'] = true;
        if (!empty($server['server_name'])) $array['sni'] = $server['server_name'];
        if (!empty($server['allow_insecure'])) $array['skip-cert-verify'] = ($server['allow_insecure'] ? true : false);
        return $array;
    }

    private function isMatch($exp, $str)
    {
        return @preg_match($exp, $str);
    }

    private function isRegex($exp)
    {
        return @preg_match($exp, null) !== false;
    }
}
