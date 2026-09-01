<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Services\TaqamulTokenService;
use Exception;

class SettingsController extends Controller
{
    protected TaqamulTokenService $tokenService;

    public function __construct(TaqamulTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Display Settings Page
     */
    public function index()
    {
        $poolAccounts = $this->tokenService->getPoolAccounts();
        $settings = [
            'telegram_bot_token' => Setting::get('telegram_bot_token', '8641043935:AAFuDvSFXZM5K7fMS-zztFH5eCSHdPugoAE'),
            'telegram_chat_id' => Setting::get('telegram_chat_id', '6363876244'),
            'auto_renew_interval' => Setting::get('auto_renew_interval', 30),
        ];

        return view('settings.index', compact('poolAccounts', 'settings'));
    }

    /**
     * Add new pool candidate account
     */
    public function addPoolAccount(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = trim($request->input('email'));
        $password = trim($request->input('password'));
        $name = trim($request->input('name', 'Pool Candidate'));

        $accounts = $this->tokenService->getPoolAccounts();
        
        // Check if already exists
        foreach ($accounts as $acc) {
            if (strtolower($acc['email'] ?? '') === strtolower($email)) {
                return redirect()->back()->with('error', 'Account already exists in the pool.');
            }
        }

        $accounts[] = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'token' => null,
            'status' => 'pending login',
        ];

        $this->tokenService->savePoolAccounts($accounts);

        return redirect()->back()->with('success', 'Candidate account added to pool successfully.');
    }

    /**
     * Remove pool candidate account
     */
    public function removePoolAccount(Request $request)
    {
        $email = trim($request->input('email'));
        $accounts = $this->tokenService->getPoolAccounts();

        $filtered = array_filter($accounts, function($acc) use ($email) {
            return strtolower($acc['email'] ?? '') !== strtolower($email);
        });

        $this->tokenService->savePoolAccounts(array_values($filtered));

        return redirect()->back()->with('success', 'Candidate account removed from pool.');
    }

    /**
     * Update system & Telegram settings
     */
    public function updateSettings(Request $request)
    {
        if ($request->has('telegram_bot_token')) {
            Setting::set('telegram_bot_token', trim($request->input('telegram_bot_token')));
        }
        if ($request->has('telegram_chat_id')) {
            Setting::set('telegram_chat_id', trim($request->input('telegram_chat_id')));
        }
        if ($request->has('auto_renew_interval')) {
            Setting::set('auto_renew_interval', (int) $request->input('auto_renew_interval'));
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
