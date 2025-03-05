<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use Illuminate\Support\Facades\Http;

class RegisterEmby extends Telegram {
    public $command = '/registeremby';
    public $description = '注册Emby账户';

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

        $email = $user->email;
        $password = $this->generatePassword(); // Use the private method to generate password
        
        $registrationResult = $this->registerEmbyUser($email);

        // Handle registration result
        if (isset($registrationResult['status']) && $registrationResult['status'] === 'success') {
            // Registration successful, return user data
            $embyId = $registrationResult['data']['Id']; // Get the Emby user Id

            // Proceed to reset the password
            $newPassword = $this->generatePassword(); // Generate a new password
            $passwordResetResult = $this->resetPassword($embyId, $newPassword);

            if ($passwordResetResult['status'] === 'success') {
            	  $user->emby_id = $embyId;
            	  $user->emby_password = $newPassword;
                $text = "注册结果: 成功\n登录地址：https://emby.bluetile.cloud\n用户名: " . $email . "\n新密码: " . $newPassword;
                $user->save();
            } else {
                $text = "密码设置失败: " . $passwordResetResult['message'];
            }
        } else {
            $text = "注册结果: " . $registrationResult['message'];
        }

        $telegramService->sendMessage($message->chat_id, $text, 'markdown');
    }

    // Encapsulate the registration process in a private function
    private function registerEmbyUser($email) {
        $host = "https://emby.bluetile.cloud";
        $path = "/emby/Users/New";
        $apiKey = "cf826812ab5d49149d813c144494a07e";

        $response = Http::withHeaders([
            'X-Emby-Token' => $apiKey,
        ])
        ->post($host . $path, [
            'Name' => $email,
            'CopyFromUserId' => 'dc6acca0f66542968fe4cf317cb663dc',
            'UserCopyOptions' => ['UserPolicy','UserConfiguration'],
        ]);

        if ($response->successful()) {
            // Return the full user data if registration is successful
            return [
                'status' => 'success',
                'data' => $response->json(), // Return full response data
                'message' => '注册成功',
            ];
        } else {
            $res = "A user with the name '{$email}' already exists.";
            if (html_entity_decode($response->body()) === $res) {
                return [
                    'status' => 'failed',
                    'message' => '用户已存在,如忘记密码请用 /resetembypassword 重置密码 ',
                ];
            }
            return [
                'status' => 'failed',
                'message' => '注册失败',
            ];
        }
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
