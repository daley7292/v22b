<?php

namespace App\Console\Commands;

use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Exception;

class SendRemindMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:remindMail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for expired subscriptions and traffic limits to users';

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
            // Fetch all users
            $users = User::all();
            $mailService = new MailService();

            // Loop through each user and send reminders if necessary
            foreach ($users as $user) {
                // Expiration reminder
                if ($user->remind_expire) {
                    $mailService->remindExpire($user);
                }

                // Traffic reminder
                if ($user->remind_traffic) {
                    $mailService->remindTraffic($user);
                }
            }
        } catch (Exception $e) {
            // Log the exception details if anything goes wrong
            Log::error('An error occurred in SendRemindMail command: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
