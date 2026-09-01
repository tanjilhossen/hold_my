<?php

namespace App\Services;

use App\Models\Passenger;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TelegramService
{
    /**
     * Check if a URL is valid for Telegram Inline Keyboard Buttons
     * Telegram strictly disallows 'localhost' and '127.0.0.1' in button URLs
     */
    protected function isValidTelegramUrl(?string $url): bool
    {
        if (empty($url)) return false;
        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            return false;
        }
        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://') || str_starts_with($url, 'tg://');
    }

    /**
     * Filter reply markup to remove invalid button URLs
     */
    protected function sanitizeReplyMarkup(?array $replyMarkup): ?array
    {
        if (!$replyMarkup || !isset($replyMarkup['inline_keyboard'])) {
            return null;
        }

        $validKeyboard = [];
        foreach ($replyMarkup['inline_keyboard'] as $row) {
            $validRow = [];
            foreach ($row as $btn) {
                if (isset($btn['url'])) {
                    if ($this->isValidTelegramUrl($btn['url'])) {
                        $validRow[] = $btn;
                    }
                } else {
                    $validRow[] = $btn;
                }
            }
            if (!empty($validRow)) {
                $validKeyboard[] = $validRow;
            }
        }

        return !empty($validKeyboard) ? ['inline_keyboard' => $validKeyboard] : null;
    }

    /**
     * Send raw text message with optional Inline Keyboard
     */
    public function sendMessage(string $text, ?string $chatId = null, ?string $botToken = null, string $parseMode = 'HTML', ?array $replyMarkup = null): array
    {
        $token = $botToken ?: Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        $chat = $chatId ?: Setting::get('telegram_chat_id', env('TELEGRAM_CHAT_ID', ''));

        if (empty($token) || empty($chat)) {
            return [
                'success' => false,
                'message' => 'Telegram Bot Token or Chat ID is not configured.'
            ];
        }

        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $payload = [
                'chat_id' => $chat,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ];

            $cleanMarkup = $this->sanitizeReplyMarkup($replyMarkup);
            if ($cleanMarkup) {
                $payload['reply_markup'] = $cleanMarkup;
            }

            $response = Http::timeout(15)->post($url, $payload);
            $body = $response->json();

            if ($response->successful() && !empty($body['ok'])) {
                return [
                    'success' => true,
                    'message' => 'Telegram message sent successfully.',
                    'data' => $body
                ];
            }

            $errorMsg = $body['description'] ?? 'Failed to send message to Telegram.';
            Log::error("Telegram Send Error: {$errorMsg}");

            return [
                'success' => false,
                'message' => $errorMsg
            ];
        } catch (Exception $e) {
            Log::error("Telegram Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send structured Candidate Notification (Success / Failed with Last Step & Retry Action)
     */
    public function sendCandidateNotification(Passenger $passenger, string $status = 'completed', string $errorMessage = ''): array
    {
        $enabled = Setting::get('telegram_notify_enabled', '1');
        if ($enabled !== '1' && $enabled !== true && $enabled !== 'true') {
            return ['success' => false, 'message' => 'Telegram notifications are disabled in settings.'];
        }

        // Notify preferences
        if ($status === 'completed' && Setting::get('telegram_notify_on_success', '1') !== '1') {
            return ['success' => false, 'message' => 'Success notifications are disabled.'];
        }
        if ($status === 'failed' && Setting::get('telegram_notify_on_failed', '1') !== '1') {
            return ['success' => false, 'message' => 'Failure notifications are disabled.'];
        }

        $submittedBy = $passenger->user ? "{$passenger->user->name} ({$passenger->user->email})" : 'System Admin';
        $timeStr = now()->timezone('Asia/Dhaka')->format('d M, Y - h:i:s A');

        if ($status === 'completed') {
            $msg = "🎉 <b>TAQAMUL ACCOUNT CREATED SUCCESSFULLY!</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "👤 <b>Candidate:</b> <code>" . htmlspecialchars($passenger->full_name) . "</code>\n";
            $msg .= "🛂 <b>Passport No:</b> <code>" . htmlspecialchars($passenger->passport_number) . "</code>\n";
            if (!empty($passenger->national_id)) {
                $msg .= "🆔 <b>National ID:</b> <code>" . htmlspecialchars($passenger->national_id) . "</code>\n";
            }
            $msg .= "📧 <b>Taqamul Email:</b> <code>" . htmlspecialchars($passenger->email) . "</code>\n";
            $msg .= "🔑 <b>Password:</b> <code>" . htmlspecialchars($passenger->password) . "</code>\n";
            if (!empty($passenger->otp_code)) {
                $msg .= "🎯 <b>Verified OTP:</b> <code>" . htmlspecialchars($passenger->otp_code) . "</code>\n";
            }
            if (!empty($passenger->phone_number)) {
                $msg .= "📱 <b>Phone:</b> <code>" . htmlspecialchars($passenger->country_code . ' ' . $passenger->phone_number) . "</code>\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "👨‍💻 <b>Submitted By:</b> " . htmlspecialchars($submittedBy) . "\n";
            $msg .= "⏰ <b>Completed At:</b> {$timeStr}\n";
            $msg .= "🌐 <b>Login:</b> https://svp-international.pacc.sa/auth/login";

            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🌐 Open Taqamul Portal',
                            'url' => 'https://svp-international.pacc.sa/auth/login'
                        ]
                    ]
                ]
            ];

            return $this->sendMessage($msg, null, null, 'HTML', $replyMarkup);
        } else {
            // Read last milestone step reached before failing
            $progFile = base_path("bot/progress_{$passenger->id}.txt");
            $lastStep = file_exists($progFile) ? trim(file_get_contents($progFile)) : 'Automated Background Processing';
            $detailedError = $errorMessage ?: ($passenger->error_message ?: 'Automated process stopped unexpectedly');

            // Generate secure one-click retry URL
            $retryToken = md5($passenger->id . config('app.key'));
            try {
                $retryUrl = route('admin.passengers.quick_retry', [
                    'id' => $passenger->id,
                    'token' => $retryToken
                ]);
            } catch (\Throwable $e) {
                $baseUrl = config('app.url', 'http://localhost');
                $retryUrl = rtrim($baseUrl, '/') . "/admin/passengers/retry/{$passenger->id}/{$retryToken}";
            }

            $msg = "❌ <b>TAQAMUL REGISTRATION FAILED!</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "👤 <b>Candidate:</b> <code>" . htmlspecialchars($passenger->full_name) . "</code>\n";
            $msg .= "🛂 <b>Passport No:</b> <code>" . htmlspecialchars($passenger->passport_number) . "</code>\n";
            $msg .= "📧 <b>Email:</b> <code>" . htmlspecialchars($passenger->email) . "</code>\n";
            $msg .= "🛑 <b>Last Step Reached:</b> <code>" . htmlspecialchars($lastStep) . "</code>\n";
            $msg .= "⚠️ <b>Error Reason:</b> <code>" . htmlspecialchars(substr($detailedError, 0, 300)) . "</code>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "👨‍💻 <b>Submitted By:</b> " . htmlspecialchars($submittedBy) . "\n";
            $msg .= "⏰ <b>Failed At:</b> {$timeStr}\n";

            // Ensure Telegram inline keyboard button uses callback_data for native zero-popup auto-retry
            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🔄 Auto-Retry Registration',
                            'callback_data' => "retry_{$passenger->id}"
                        ]
                    ],
                    [
                        [
                            'text' => '🌐 Open Taqamul Portal',
                            'url' => 'https://svp-international.pacc.sa/auth/login'
                        ]
                    ]
                ]
            ];

            return $this->sendMessage($msg, null, null, 'HTML', $replyMarkup);
        }
    }

    /**
     * Test Bot Connection
     */
    public function testConnection(string $botToken, string $chatId): array
    {
        $testMsg = "🚀 <b>Taqamul Bot Telegram Test Notification</b>\n";
        $testMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
        $testMsg .= "✅ <b>Telegram Bot API is connected successfully!</b>\n";
        $testMsg .= "⏰ <b>Timestamp:</b> " . now()->timezone('Asia/Dhaka')->format('d M, Y - h:i:s A') . "\n";
        $testMsg .= "⚡ <i>Candidate registration alerts and Auto-Retry buttons will be sent to this chat.</i>";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🌐 Open Taqamul Portal',
                        'url' => 'https://svp-international.pacc.sa/auth/login'
                    ]
                ]
            ]
        ];

        return $this->sendMessage($testMsg, $chatId, $botToken, 'HTML', $replyMarkup);
    }
}
