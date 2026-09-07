<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TaqamulTokenService;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class FastHttpLoginCommand extends Command
{
    protected $signature = 'taqamul:fast-login {email} {password}';
    protected $description = 'Fast pure HTTP API login for Taqamul candidate account';

    public function handle(TaqamulTokenService $tokenService)
    {
        $email = $this->argument('email');
        $rawPass = $this->argument('password');
        $b64Decoded = base64_decode($rawPass, true);
        $password = ($b64Decoded !== false && base64_encode($b64Decoded) === $rawPass) ? $b64Decoded : $rawPass;
        $logFile = storage_path('app/bot_login_stream.log');

        $log = function ($msg) use ($logFile) {
            @file_put_contents($logFile, $msg . "\n", FILE_APPEND);
            $this->info($msg);
        };

        @file_put_contents($logFile, "[Token Bot] Starting Pure HTTP API Login for: {$email}...\n");

        $token = $tokenService->loginAndFetchTokenHttp($email, $password, $log);

        if ($token) {
            Setting::set('slot_checker_global_saved_token', $token);
            $tokenService->updateAccountToken($email, $token);
            $log("[Token Bot 🔑] Login Successful! Token acquired.");
            $log("FINAL_TOKEN_RESULT:" . json_encode(['success' => true, 'token' => $token, 'email' => $email]));
            return 0;
        }

        $log("[Token Bot ❌] Login Failed for {$email}. Check laravel.log.");
        $log("FINAL_TOKEN_RESULT:" . json_encode(['success' => false, 'error' => 'Pure HTTP Login failed.']));
        return 1;
    }
}
