<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Facades\DB;

class V2boardInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'v2board 安装';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->info("__     ______  ____                      _  ");
            $this->info("\ \   / /___ \| __ )  ___   __ _ _ __ __| | ");
            $this->info(" \ \ / /  __) |  _ \ / _ \ / _` | '__/ _` | ");
            $this->info("  \ V /  / __/| |_) | (_) | (_| | | | (_| | ");
            $this->info("   \_/  |_____|____/ \___/ \__,_|_|  \__,_| ");
            if (\File::exists(base_path() . '/.env')) {
                $securePath = config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))));
                $this->info("访问 http(s)://你的站点/{$securePath} 进入管理面板，你可以在用户中心修改你的密码。");
                abort(500, '如需重新安装请删除目录下.env文件');
            }

            if (!copy(base_path() . '/.env.example', base_path() . '/.env')) {
                abort(500, '复制环境文件失败，请检查目录权限');
            }
            $this->saveToEnv([
                'APP_KEY' => 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')),
                'DB_HOST' => $this->ask('请输入数据库地址（默认:localhost）', 'localhost'),
                'DB_DATABASE' => $this->ask('请输入数据库名'),
                'DB_USERNAME' => $this->ask('请输入数据库用户名'),
                'DB_PASSWORD' => $this->ask('请输入数据库密码')
            ]);
            \Artisan::call('config:clear');
            \Artisan::call('config:cache');
            try {
                DB::connection()->getPdo();
            } catch (\Exception $e) {
                abort(500, '数据库连接失败');
            }
            $file = \File::get(base_path() . '/database/install.sql');
            if (!$file) {
                abort(500, '数据库文件不存在');
            }

            // 移除注释和多余的空格
            $file = preg_replace('/\/\*.*?\*\//s', '', $file); // 移除多行注释
            $file = preg_replace('/--.*$/m', '', $file); // 移除单行注释
            $file = preg_replace('/^\s*$/m', '', $file); // 移除空行

            // 按分号分割，但排除字符串中的分号
            $sql = [];
            $current = '';
            $inString = false;
            $stringChar = '';
            $escaped = false;

            for ($i = 0; $i < strlen($file); $i++) {
                $char = $file[$i];
                
                if ($escaped) {
                    $current .= $char;
                    $escaped = false;
                    continue;
                }
                
                if ($char === '\\') {
                    $escaped = true;
                    $current .= $char;
                    continue;
                }
                
                if (($char === "'" || $char === '"') && !$escaped) {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } else if ($char === $stringChar) {
                        $inString = false;
                    }
                }
                
                if ($char === ';' && !$inString) {
                    $current = trim($current);
                    if (!empty($current)) {
                        $sql[] = $current;
                    }
                    $current = '';
                } else {
                    $current .= $char;
                }
            }

            // 添加最后一个语句（如果没有以分号结尾）
            $current = trim($current);
            if (!empty($current)) {
                $sql[] = $current;
            }

            if (empty($sql)) {
                abort(500, '没有找到有效的 SQL 语句');
            }

            $this->info('正在导入数据库请稍等...');
            $errors = [];

            foreach ($sql as $index => $item) {
                try {
                    if (empty(trim($item))) {
                        continue;
                    }
                    DB::statement($item);
                } catch (\Exception $e) {
                    $errors[] = "SQL 语句 #{$index} 执行失败: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                $this->error('数据库导入过程中出现以下错误：');
                foreach ($errors as $error) {
                    $this->error($error);
                }
                abort(500, '数据库导入失败');
            }

            $this->info('数据库导入完成');
            $email = '';
            while (!$email) {
                $email = $this->ask('请输入管理员邮箱?');
            }
            $password = Helper::guid(false);
            if (!$this->registerAdmin($email, $password)) {
                abort(500, '管理员账号注册失败，请重试');
            }

            $this->info('一切就绪');
            $this->info("管理员邮箱：{$email}");
            $this->info("管理员密码：{$password}");

            $defaultSecurePath = hash('crc32b', config('app.key'));
            $this->info("访问 http(s)://你的站点/{$defaultSecurePath} 进入管理面板，你可以在用户中心修改你的密码。");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function registerAdmin($email, $password)
    {
        $user = new User();
        $user->email = $email;
        if (strlen($password) < 8) {
            abort(500, '管理员密码长度最小为8位字符');
        }
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 1;
        return $user->save();
    }

    private function saveToEnv($data = [])
    {
        function set_env_var($key, $value)
        {
            if (! is_bool(strpos($value, ' '))) {
                $value = '"' . $value . '"';
            }
            $key = strtoupper($key);

            $envPath = app()->environmentFilePath();
            $contents = file_get_contents($envPath);

            preg_match("/^{$key}=[^\r\n]*/m", $contents, $matches);

            $oldValue = count($matches) ? $matches[0] : '';

            if ($oldValue) {
                $contents = str_replace("{$oldValue}", "{$key}={$value}", $contents);
            } else {
                $contents = $contents . "\n{$key}={$value}\n";
            }

            $file = fopen($envPath, 'w');
            fwrite($file, $contents);
            return fclose($file);
        }
        foreach($data as $key => $value) {
            set_env_var($key, $value);
        }
        return true;
    }
}
