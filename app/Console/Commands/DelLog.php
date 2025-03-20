<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DelLog extends Command
{
    protected $signature = 'del:log';
    protected $description = '删除v2_mail_log以及v2_log日志';

    public function handle()
    {
        try {
            // 提示用户确认
            if (!$this->confirm('此操作将清空 v2_mail_log 和 v2_log 数据表，是否继续?')) {
                $this->info('操作已取消');
                return;
            }

            // 执行删除操作
            $mailLogCount = DB::table('v2_mail_log')->count();
            $logCount = DB::table('v2_log')->count();

            DB::table('v2_mail_log')->truncate();
            DB::table('v2_log')->truncate();

            $this->info(sprintf(
                "成功清空日志表:\nv2_mail_log: %d 条记录\nv2_log: %d 条记录", 
                $mailLogCount,
                $logCount
            ));
            
        } catch (\Exception $e) {
            $this->error('删除日志失败: ' . $e->getMessage());
        }
    }
}