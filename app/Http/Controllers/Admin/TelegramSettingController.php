<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramSettingController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Display Telegram Settings Page
     */
    public function index()
    {
        $settings = [
            'telegram_bot_token' => Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', '')),
            'telegram_chat_id' => Setting::get('telegram_chat_id', env('TELEGRAM_CHAT_ID', '')),
            'telegram_notify_enabled' => Setting::get('telegram_notify_enabled', '1'),
            'telegram_notify_on_success' => Setting::get('telegram_notify_on_success', '1'),
            'telegram_notify_on_failed' => Setting::get('telegram_notify_on_failed', '1'),
        ];

        $isConnected = !empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id']);

        return view('admin.telegram.index', compact('settings', 'isConnected'));
    }

    /**
     * Update Telegram Settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'telegram_bot_token' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'telegram_notify_enabled' => 'nullable|in:0,1',
            'telegram_notify_on_success' => 'nullable|in:0,1',
            'telegram_notify_on_failed' => 'nullable|in:0,1',
        ]);

        Setting::set('telegram_bot_token', trim($request->input('telegram_bot_token', '')));
        Setting::set('telegram_chat_id', trim($request->input('telegram_chat_id', '')));
        Setting::set('telegram_notify_enabled', $request->has('telegram_notify_enabled') ? '1' : '0');
        Setting::set('telegram_notify_on_success', $request->has('telegram_notify_on_success') ? '1' : '0');
        Setting::set('telegram_notify_on_failed', $request->has('telegram_notify_on_failed') ? '1' : '0');

        return redirect()->route('admin.telegram.index')->with('success', 'Telegram automation settings updated successfully!');
    }

    /**
     * Send Test Message to Telegram
     */
    public function test(Request $request)
    {
        $botToken = trim($request->input('telegram_bot_token', Setting::get('telegram_bot_token')));
        $chatId = trim($request->input('telegram_chat_id', Setting::get('telegram_chat_id')));

        if (empty($botToken) || empty($chatId)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter both Bot API Token and Chat ID before testing.'
            ], 422);
        }

        $res = $this->telegramService->testConnection($botToken, $chatId);

        return response()->json($res);
    }
}
