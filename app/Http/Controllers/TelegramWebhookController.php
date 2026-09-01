<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Passenger;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle Telegram Bot Webhook Updates (Callback Queries, Inline Buttons)
     */
    public function handleWebhook(Request $request)
    {
        $update = $request->all();
        Log::info("Telegram Webhook Received:", $update);

        if (isset($update['callback_query'])) {
            $cq = $update['callback_query'];
            $cqId = $cq['id'];
            $data = $cq['data'] ?? '';
            $botToken = Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));

            if (str_starts_with($data, 'retry_')) {
                $passengerId = (int) str_replace('retry_', '', $data);
                $passenger = Passenger::find($passengerId);

                if ($passenger) {
                    // Reset progress file
                    $progFile = base_path("bot/progress_{$passenger->id}.txt");
                    if (file_exists($progFile)) {
                        @unlink($progFile);
                    }

                    $passenger->update([
                        'status' => 'processing',
                        'error_message' => null,
                    ]);

                    // Launch background process
                    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
                        $phpBin = file_exists("D:\\xampp\\php\\php.exe") ? "D:\\xampp\\php\\php.exe" : "php";
                        $artisan = base_path('artisan');
                        $cmd = "start \"\" \"{$phpBin}\" \"{$artisan}\" taqamul:process-passenger {$passenger->id} > NUL 2>&1";
                        @pclose(popen($cmd, "r"));
                    } else {
                        $artisan = base_path('artisan');
                        $cmd = "php \"{$artisan}\" taqamul:process-passenger {$passenger->id} > /dev/null 2>&1 &";
                        @exec($cmd);
                    }

                    // 1. Send instant popup toast inside Telegram app (No browser tab!)
                    if ($botToken) {
                        Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
                            'callback_query_id' => $cqId,
                            'text' => "🔄 Auto-Retry Started for {$passenger->full_name}!",
                            'show_alert' => false
                        ]);
                    }

                    // 2. Notify channel
                    try {
                        app(TelegramService::class)->sendMessage("🔄 <b>Auto-Retry Initiated via Telegram!</b>\n━━━━━━━━━━━━━━━━━━━━\n👤 Candidate: <code>{$passenger->full_name}</code>\n🛂 Passport: <code>{$passenger->passport_number}</code>\n⚡ <i>Background bot restarted...</i>");
                    } catch (\Exception $e) {}

                    return response()->json(['status' => 'ok']);
                }
            }

            if ($botToken) {
                Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
                    'callback_query_id' => $cqId,
                    'text' => 'Action processed',
                    'show_alert' => false
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Auto-register Webhook URL with Telegram
     */
    public static function registerWebhook(): bool
    {
        $token = Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        $baseUrl = config('app.url', 'https://tqml.renonx.tech');
        
        if (empty($token)) return false;

        // Telegram Webhook strictly requires HTTPS without non-standard ports
        if (str_starts_with($baseUrl, 'http://')) {
            $baseUrl = str_replace('http://', 'https://', $baseUrl);
        }
        $baseUrl = preg_replace('/:5556$/', '', $baseUrl);

        $webhookUrl = rtrim($baseUrl, '/') . '/api/telegram/webhook';
        try {
            $res = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl
            ]);
            Log::info("Telegram Webhook Registered [{$webhookUrl}]: " . json_encode($res->json()));
            return $res->successful();
        } catch (\Exception $e) {
            Log::error("Failed to set Telegram webhook: " . $e->getMessage());
            return false;
        }
    }
}
