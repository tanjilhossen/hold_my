<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Passenger;
use App\Services\WafidMailService;
use App\Services\TaqamulTokenService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    protected WafidMailService $wafidMailService;
    protected TaqamulTokenService $tokenService;

    public function __construct(WafidMailService $wafidMailService, TaqamulTokenService $tokenService)
    {
        $this->wafidMailService = $wafidMailService;
        $this->tokenService = $tokenService;
    }

    /**
     * Show settings dashboard
     */
    public function index()
    {
        $settings = [
            'default_email_provision_method' => Setting::get('default_email_provision_method', 'auto_wafidmail'),
            'default_otp_delivery_method' => Setting::get('default_otp_delivery_method', 'email'),
            'browser_mode' => Setting::get('browser_mode', 'headless'),
            'wafid_mail_base_url' => Setting::get('wafid_mail_base_url', env('WAFID_MAIL_BASE_URL', 'https://mail.wafidmaster.com')),
            'wafid_mail_key_id' => Setting::get('wafid_mail_key_id', env('WAFID_MAIL_KEY_ID', '')),
            'wafid_mail_secret_key' => Setting::get('wafid_mail_secret_key', env('WAFID_MAIL_SECRET_KEY', '')),
            'capsolver_api_key' => Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', '')),
            'default_password' => Setting::get('default_password', 'Taqamul@2026!'),
            'slot_checker_manual_token' => Setting::get('slot_checker_manual_token', ''),
            'hide_hashes' => Setting::get('hide_hashes', '0'),
        ];

        $wafidStatus = $this->wafidMailService->isConfigured();
        $poolAccounts = $this->tokenService->getPoolAccounts();
        $allPassengers = Passenger::excludePool()->whereNotNull('email')->whereNotNull('password')->latest()->take(50)->get();

        return view('admin.settings.index', compact('settings', 'wafidStatus', 'poolAccounts', 'allPassengers'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'default_email_provision_method' => 'required|in:auto_wafidmail,auto_yopmail,custom_mail',
            'default_otp_delivery_method' => 'required|in:email,phone',
            'browser_mode' => 'required|in:headless,headed',
            'wafid_mail_base_url' => 'nullable|url',
            'wafid_mail_key_id' => 'nullable|string',
            'wafid_mail_secret_key' => 'nullable|string',
            'capsolver_api_key' => 'nullable|string',
            'default_password' => 'nullable|string',
            'slot_checker_manual_token' => 'nullable|string',
        ]);

        $keys = [
            'default_email_provision_method',
            'default_otp_delivery_method',
            'browser_mode',
            'wafid_mail_base_url',
            'wafid_mail_key_id',
            'wafid_mail_secret_key',
            'capsolver_api_key',
            'default_password',
            'slot_checker_manual_token',
            'hide_hashes',
        ];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, trim($request->input($key)));
            }
        }

        return redirect()->route('admin.settings.index')->with('success', 'Settings updated successfully.');
    }

    /**
     * AJAX Toggle for Hide Hashes (Privacy Mode)
     */
    public function toggleHideHashes(Request $request)
    {
        $enabled = $request->boolean('hide_hashes');
        Setting::set('hide_hashes', $enabled ? '1' : '0');

        return response()->json([
            'success' => true,
            'hide_hashes' => $enabled,
            'message' => $enabled 
                ? 'Privacy Mode Enabled: All mother hashes are now masked.' 
                : 'Privacy Mode Disabled: Hashes are now visible.',
        ]);
    }

    /**
     * Add candidate to slot checking pool
     */
    public function addPoolCandidate(Request $request)
    {
        $request->validate([
            'passenger_id' => 'nullable|exists:passengers,id',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $accounts = $this->tokenService->getPoolAccounts();
        $email = trim($request->input('email'));

        // Check if already in pool
        foreach ($accounts as $acc) {
            if (strtolower($acc['email']) === strtolower($email)) {
                return back()->with('error', 'This account is already added to the Slot Checker Pool.');
            }
        }

        $name = 'Candidate';
        if ($request->passenger_id) {
            $p = Passenger::find($request->passenger_id);
            if ($p) $name = $p->full_name;
        }

        $accounts[] = [
            'id' => $request->passenger_id,
            'name' => $name,
            'email' => $email,
            'password' => $request->input('password'),
            'token' => null,
            'token_expires_at' => null,
            'status' => 'idle',
        ];

        $this->tokenService->savePoolAccounts($accounts);

        return back()->with('success', 'Candidate account successfully added to Slot Checker Pool!');
    }

    /**
     * Remove candidate from slot checking pool
     */
    public function removePoolCandidate(Request $request)
    {
        $email = trim($request->input('email'));
        $accounts = $this->tokenService->getPoolAccounts();

        $filtered = array_filter($accounts, function ($acc) use ($email) {
            return strtolower($acc['email']) !== strtolower($email);
        });

        $this->tokenService->savePoolAccounts($filtered);

        return back()->with('success', 'Account has been removed from the Slot Checker Pool.');
    }

    /**
     * Test Login & Fetch Bearer Token for a specific candidate
     */
    public function testPoolLogin(Request $request)
    {
        $email = trim($request->input('email'));
        $accounts = $this->tokenService->getPoolAccounts();

        $target = null;
        $targetIndex = null;
        foreach ($accounts as $i => $acc) {
            if (strtolower($acc['email']) === strtolower($email)) {
                $target = $acc;
                $targetIndex = $i;
                break;
            }
        }

        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Account not found in pool'], 404);
        }

        $token = $this->tokenService->loginAndFetchToken($target['email'], $target['password']);

        if (!empty($token)) {
            $accounts[$targetIndex]['token'] = $token;
            $accounts[$targetIndex]['token_expires_at'] = now()->addHours(12)->toDateTimeString();
            $accounts[$targetIndex]['status'] = 'active';
            $this->tokenService->savePoolAccounts($accounts);

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged in and Bearer Token acquired!',
                'token' => $token
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Automatic login failed. Please verify credentials or 2Captcha key.'
        ], 500);
    }

    /**
     * Start Bulk Pool Generator Bot in Background
     */
    public function startBulkPoolGenerator(Request $request)
    {
        $count = (int)$request->input('count', 50);
        if ($count < 1) $count = 50;

        $statusFile = storage_path('app/bulk_pool_status.json');
        @file_put_contents($statusFile, json_encode([
            'running' => true,
            'target' => $count,
            'current' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'started_at' => now()->toDateTimeString(),
            'last_account' => null,
            'logs' => [
                '[' . now()->format('h:i:s A') . '] 🚀 Bulk Candidate Pool Generator initialized for ' . $count . ' accounts...'
            ]
        ], JSON_PRETTY_PRINT));

        // Kill previous node generator if running
        @shell_exec('taskkill /F /IM node.exe 2>&1');

        // Launch background node script
        $botDir = base_path('bot');
        $scriptFile = 'bulk_pool_generator.js';
        $logFile = storage_path('logs/bulk_pool.log');
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $cmd = "cd /d \"{$botDir}\" && start /B node \"{$scriptFile}\" --count={$count} > \"{$logFile}\" 2>&1";
            @pclose(popen($cmd, "r"));
        } else {
            $cmd = "cd \"{$botDir}\" && node \"{$scriptFile}\" --count={$count} > \"{$logFile}\" 2>&1 &";
            @exec($cmd);
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk Pool Generator started for {$count} accounts! Accounts will be added live.",
            'target' => $count
        ]);
    }

    /**
     * Get live progress of Bulk Pool Generator & Current Pool Accounts
     */
    public function getBulkPoolStatus()
    {
        $statusFile = storage_path('app/bulk_pool_status.json');
        $status = [
            'running' => false,
            'target' => 0,
            'current' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'logs' => [],
            'last_account' => null
        ];

        if (file_exists($statusFile)) {
            $json = @json_decode(file_get_contents($statusFile), true);
            if (is_array($json)) {
                $status = array_merge($status, $json);
            }
        }

        $poolAccounts = $this->tokenService->getPoolAccounts();

        return response()->json([
            'success' => true,
            'status' => $status,
            'total_pool_accounts' => count($poolAccounts),
            'pool_accounts' => $poolAccounts
        ]);
    }

    /**
     * Stop Bulk Pool Generator Bot
     */
    public function stopBulkPoolGenerator()
    {
        $statusFile = storage_path('app/bulk_pool_status.json');
        if (file_exists($statusFile)) {
            $json = @json_decode(file_get_contents($statusFile), true) ?: [];
            $json['running'] = false;
            $json['logs'][] = '[' . now()->format('h:i:s A') . '] 🛑 Bulk Pool Generator stopped by admin.';
            @file_put_contents($statusFile, json_encode($json, JSON_PRETTY_PRINT));
        }

        @shell_exec('taskkill /F /IM node.exe 2>&1');

        return response()->json([
            'success' => true,
            'message' => 'Bulk Pool Generator has been stopped.'
        ]);
    }
}
