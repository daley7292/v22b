<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use Illuminate\Support\Facades\Http;

class ResetEmbyPassword extends Telegram {
    public $command = '/resetembypassword';
    public $description = '重置Emby账户密码';

    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;

        if (!$message->is_private) {
            return;
        }

        $user = User::where('telegram_id', $message->chat_id)->first();
        if (!$user) {
            $telegramService->sendMessage($message->chat_id, '没有查询到您的用户信息，请先绑定账号', 'markdown');
            return;
        }

        // Check if the user has an associated Emby account
        if (empty($user->emby_id)) {
            $telegramService->sendMessage($message->chat_id, '您的账户没有关联Emby账号，无法重置密码', 'markdown');
            return;
        }

        $newPassword = $this->generatePassword(); // Generate a new password

        // Proceed to reset the password for the Emby user
        $passwordResetResult = $this->resetPassword($user->emby_id, $newPassword);

        // Handle password reset result
        if ($passwordResetResult['status'] === 'success') {
            // Update the user's Emby password in the database
            $user->emby_password = $newPassword;
            $user->save();

            $text = "密码重置成功\n新密码: " . $newPassword . "\n请使用此新密码登录您的Emby账户。";
        } else {
            $text = "密码重置失败: " . $passwordResetResult['message'];
        }

        $telegramService->sendMessage($message->chat_id, $text, 'markdown');
    }

    // Encapsulate the password reset process in a private function
    private function resetPassword($embyId, $newPassword) {
        $host = "https://emby.bluetile.cloud";
        $path = "/emby/Users/{$embyId}/Password"; // Password reset endpoint
        $apiKey = "cf826812ab5d49149d813c144494a07e";

        $response = Http::withHeaders([
            'X-Emby-Token' => $apiKey,
        ])
        ->post($host . $path, [
            'NewPw' => $newPassword, // New password parameter
        ]);

        if ($response->successful()) {
            return [
                'status' => 'success',
                'message' => '密码重置成功',
            ];
        } else {
            return [
                'status' => 'failed',
                'message' => '密码重置失败',
            ];
        }
    }

    private function generatePassword($length = 16) {
        $characters = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ'; 
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $password;
    }
}
