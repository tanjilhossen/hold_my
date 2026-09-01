<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Services\TempMailService;
use App\Services\WafidMailService;
use App\Services\YopmailService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PassengerController extends Controller
{
    protected TempMailService $tempMailService;
    protected WafidMailService $wafidMailService;
    protected YopmailService $yopmailService;

    public function __construct(
        TempMailService $tempMailService, 
        WafidMailService $wafidMailService, 
        YopmailService $yopmailService
    ) {
        $this->tempMailService = $tempMailService;
        $this->wafidMailService = $wafidMailService;
        $this->yopmailService = $yopmailService;
    }

    /**
     * Display a listing of passengers
     */
    public function index(Request $request)
    {
        if (function_exists('session') && session()->isStarted()) {
            session()->save();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $query = Passenger::excludePool()->with('user')->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('passport_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $passengers = $query->paginate(15)->withQueryString();

        return view('admin.passengers.index', compact('passengers'));
    }

    /**
     * Get live statuses of recent passengers for real-time frontend auto-update
     */
    public function liveStatuses()
    {
        $passengers = Passenger::excludePool()
            ->select('id', 'status', 'email', 'password', 'otp_code', 'error_message', 'first_name', 'last_name')
            ->latest()
            ->take(30)
            ->get();

        foreach ($passengers as $p) {
            $progFile = base_path("bot/progress_{$p->id}.txt");
            if ($p->status === 'processing' && file_exists($progFile)) {
                $p->progress_text = trim(file_get_contents($progFile));
            } else {
                $p->progress_text = ($p->status === 'processing') ? 'Processing...' : null;
            }
        }

        $stats = [
            'total' => Passenger::excludePool()->count(),
            'completed' => Passenger::excludePool()->where('status', 'Completed')->count(),
            'ac_done' => Passenger::excludePool()->whereIn('status', ['AC Done', 'ac_done'])->count(),
            'mailboxes' => Passenger::excludePool()->whereNotNull('email')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'passengers' => $passengers
        ]);
    }

    /**
     * Display the specified passenger profile
     */
    public function show($id)
    {
        $passenger = Passenger::with('user')->findOrFail($id);
        return view('admin.passengers.show', compact('passenger'));
    }

    /**
     * Display Direct In-Browser Live Auto-Login Tab
     */
    public function portalBridge($id)
    {
        $passenger = Passenger::with('user')->findOrFail($id);
        $capsolverKey = \App\Models\Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', ''));
        return view('admin.passengers.portal_bridge', compact('passenger', 'capsolverKey'));
    }

    /**
     * Display Auto-Login Portal & Live Automation Assistant
     */
    public function autoLogin($id)
    {
        return $this->portalBridge($id);
    }

    /**
     * Launch Autonomous Headed Browser Auto-Login Bot
     */
    public function startAutoLoginBot($id)
    {
        $passenger = Passenger::findOrFail($id);
        $botDir = base_path('bot');
        $botScript = $botDir . '/taqamul_login_bot.js';
        $tempJson = $botDir . '/login_temp_' . uniqid() . '.json';

        $data = [
            'passenger_id' => $passenger->id,
            'email' => $passenger->email,
            'password' => $passenger->password,
            'temp_mail_password' => $passenger->temp_mail_password,
            'capsolver_api_key' => \App\Models\Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', '')),
            'wafid_mail_base_url' => \App\Models\Setting::get('wafid_mail_base_url', env('WAFID_MAIL_BASE_URL', '')),
            'wafid_mail_key_id' => \App\Models\Setting::get('wafid_mail_key_id', env('WAFID_MAIL_KEY_ID', '')),
            'wafid_mail_secret_key' => \App\Models\Setting::get('wafid_mail_secret_key', env('WAFID_MAIL_SECRET_KEY', '')),
        ];
        file_put_contents($tempJson, json_encode($data, JSON_PRETTY_PRINT));

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B node \"{$botScript}\" \"{$tempJson}\" > NUL 2>&1", "r"));
        } else {
            exec("node \"{$botScript}\" \"{$tempJson}\" > /dev/null 2>&1 &");
        }

        return response()->json([
            'success' => true,
            'message' => 'Autonomous Login Bot launched on screen! Auto-filling credentials, solving CAPTCHA and verifying OTP...',
        ]);
    }

    /**
     * Check incoming mailbox messages & OTP for a passenger
     */
    public function checkInbox($id)
    {
        $passenger = Passenger::findOrFail($id);

        // Check if Private Wafid Mail (@wafidmaster.com or @renonx.tech)
        if (str_contains(strtolower($passenger->email), '@wafidmaster.com') || str_contains(strtolower($passenger->email), '@renonx.tech')) {
            $mailboxName = preg_replace('/@.*$/', '', $passenger->email);
            $messages = $this->wafidMailService->getMessages($mailboxName);
            $otp = $this->wafidMailService->waitForLatestOtp($mailboxName, 10);
            if (!empty($otp)) {
                $passenger->update(['otp_code' => $otp]);
            }
            return response()->json([
                'success' => true,
                'email' => $passenger->email,
                'temp_mail_password' => 'Private Wafid Mail API',
                'otp' => $otp,
                'messages_count' => count($messages),
                'messages' => $messages,
            ]);
        }

        // Check if YOPmail
        if (str_contains(strtolower($passenger->email), '@yopmail.com')) {
            $res = $this->yopmailService->checkOtp($passenger->email);
            $otp = $res['otp'] ?? null;
            if (!empty($otp)) {
                $passenger->update(['otp_code' => $otp]);
            }
            return response()->json([
                'success' => true,
                'email' => $passenger->email,
                'temp_mail_password' => 'Direct Access (YOPmail)',
                'otp' => $otp,
                'messages_count' => !empty($otp) ? 1 : 0,
                'messages' => !empty($otp) ? [
                    [
                        'subject' => $res['subject'] ?? 'Taqamul Verification Code',
                        'intro' => $res['preview'] ?? 'Verification Code: ' . $otp,
                        'createdAt' => date('Y-m-d H:i:s')
                    ]
                ] : [],
            ]);
        }

        if (!$passenger->temp_mail_token) {
            return response()->json([
                'success' => false,
                'message' => 'No Temp-Mail token stored for this candidate.',
            ]);
        }

        $messages = $this->tempMailService->getMessages($passenger->temp_mail_token);
        $otp = $this->tempMailService->fetchLatestOtp($passenger->temp_mail_token);

        return response()->json([
            'success' => true,
            'email' => $passenger->email,
            'temp_mail_password' => $passenger->temp_mail_password,
            'otp' => $otp,
            'messages_count' => count($messages),
            'messages' => $messages,
        ]);
    }

    /**
     * Public API endpoint to get OTP for client-side auto-login (CORS enabled)
     */
    public function apiCheckOtp($id)
    {
        $passenger = Passenger::find($id);
        if (!$passenger) {
            return response()->json(['success' => false, 'message' => 'Passenger not found'], 404);
        }

        $otp = null;
        if (str_contains(strtolower($passenger->email), '@wafidmaster.com') || str_contains(strtolower($passenger->email), '@renonx.tech')) {
            $mailboxName = preg_replace('/@.*$/', '', $passenger->email);
            $otp = $this->wafidMailService->waitForLatestOtp($mailboxName, 3);
        } elseif (str_contains(strtolower($passenger->email), '@yopmail.com')) {
            $res = $this->yopmailService->checkOtp($passenger->email);
            $otp = $res['otp'] ?? null;
        }

        if (!empty($otp)) {
            $passenger->update(['otp_code' => $otp]);
        } else {
            $otp = $passenger->otp_code;
        }

        return response()->json([
            'success' => true,
            'id' => $passenger->id,
            'email' => $passenger->email,
            'otp' => $otp,
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    }

    /**
     * JSONP endpoint for browsers to bypass Mixed Content (HTTPS -> HTTP)
     */
    public function apiCheckOtpJsonp(Request $request, $id)
    {
        $callback = $request->query('callback', 'handleOtp');
        $passenger = Passenger::find($id);
        $otp = null;

        if ($passenger) {
            if (str_contains(strtolower($passenger->email), '@wafidmaster.com') || str_contains(strtolower($passenger->email), '@renonx.tech')) {
                $mailboxName = preg_replace('/@.*$/', '', $passenger->email);
                $otp = $this->wafidMailService->waitForLatestOtp($mailboxName, 2);
            } elseif (str_contains(strtolower($passenger->email), '@yopmail.com')) {
                $res = $this->yopmailService->checkOtp($passenger->email);
                $otp = $res['otp'] ?? null;
            }

            if (!empty($otp)) {
                $passenger->update(['otp_code' => $otp]);
            } else {
                $otp = $passenger->otp_code;
            }
        }

        $data = [
            'success' => $passenger ? true : false,
            'id' => $id,
            'otp' => $otp,
        ];

        $json = json_encode($data);
        return response("{$callback}({$json});", 200, [
            'Content-Type' => 'application/javascript',
            'Access-Control-Allow-Origin' => '*'
        ]);
    }

    /**
     * Solve Google reCAPTCHA v2 securely on server-side without exposing CapSolver key to client
     */
    public function apiSolveCaptcha()
    {
        $capsolverKey = \App\Models\Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', ''));
        if (empty($capsolverKey)) {
            return response()->json(['success' => false, 'message' => 'CapSolver API key not configured'], 400);
        }

        try {
            $taskRes = \Illuminate\Support\Facades\Http::post('https://api.capsolver.com/createTask', [
                'clientKey' => $capsolverKey,
                'task' => [
                    'type' => 'ReCaptchaV2TaskProxyLess',
                    'websiteURL' => 'https://svp-international.pacc.sa/auth/login?role=labor',
                    'websiteKey' => '6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy',
                ]
            ])->json();

            if (!isset($taskRes['taskId'])) {
                return response()->json(['success' => false, 'message' => 'CapSolver task creation failed: ' . json_encode($taskRes)], 500);
            }

            $taskId = $taskRes['taskId'];
            for ($i = 0; $i < 60; $i++) {
                usleep(300000); // 300ms
                $result = \Illuminate\Support\Facades\Http::post('https://api.capsolver.com/getTaskResult', [
                    'clientKey' => $capsolverKey,
                    'taskId' => $taskId
                ])->json();

                if (isset($result['status']) && $result['status'] === 'ready') {
                    return response()->json([
                        'success' => true,
                        'token' => $result['solution']['gRecaptchaResponse']
                    ])->header('Access-Control-Allow-Origin', '*');
                }

                if (isset($result['status']) && $result['status'] === 'failed') {
                    return response()->json(['success' => false, 'message' => 'CapSolver failed'], 500);
                }
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => false, 'message' => 'CapSolver timeout'], 504);
    }

    /**
     * JSONP endpoint for server-side captcha solver
     */
    public function apiSolveCaptchaJsonp(Request $request)
    {
        $callback = $request->query('callback', 'handleCaptcha');
        $res = $this->apiSolveCaptcha();
        $json = $res->getContent();
        return response("{$callback}({$json});", 200, [
            'Content-Type' => 'application/javascript',
            'Access-Control-Allow-Origin' => '*'
        ]);
    }

    /**
     * Retry automated candidate registration in background
     */
    public function retryRegistration($id)
    {
        $passenger = Passenger::findOrFail($id);
        $passenger->update([
            'status' => 'processing',
            'error_message' => null
        ]);

        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $phpBin = file_exists("D:\\xampp\\php\\php.exe") ? "D:\\xampp\\php\\php.exe" : "php";
            $artisan = base_path('artisan');
            $cmd = "start /B \"\" \"{$phpBin}\" \"{$artisan}\" taqamul:process-passenger {$passenger->id} > NUL 2>&1";
            @pclose(popen($cmd, "r"));
        } else {
            $artisan = base_path('artisan');
            $cmd = "php \"{$artisan}\" taqamul:process-passenger {$passenger->id} > /dev/null 2>&1 &";
            @exec($cmd);
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Retrying registration for {$passenger->full_name} in background...",
            ]);
        }

        return redirect()->back()->with('success', "Retrying registration for {$passenger->full_name} in background...");
    }

    /**
     * Remove the specified passenger
     */
    public function destroy($id)
    {
        $passenger = Passenger::findOrFail($id);
        $passenger->delete();

        return redirect()->route('admin.passengers.index')->with('success', 'Passenger deleted successfully.');
    }

    /**
     * Quick Retry Trigger (Used by Telegram Direct Action & Web)
     */
    public function quickRetry(Request $request, $id)
    {
        $passenger = Passenger::findOrFail($id);
        $token = $request->query('token');
        $expectedToken = md5($passenger->id . config('app.key'));

        // Check authentication or valid signed token
        if (!Auth::check() && $token !== $expectedToken) {
            abort(403, 'Unauthorized retry link.');
        }

        // Reset progress file
        $progFile = base_path("bot/progress_{$passenger->id}.txt");
        if (file_exists($progFile)) {
            @unlink($progFile);
        }

        $passenger->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        // Launch background command (Correct signature: taqamul:process-passenger)
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $phpBin = file_exists("D:\\xampp\\php\\php.exe") ? "D:\\xampp\\php\\php.exe" : "php";
            $artisan = base_path('artisan');
            $cmd = "start /B \"\" \"{$phpBin}\" \"{$artisan}\" taqamul:process-passenger {$passenger->id} > NUL 2>&1";
            @pclose(popen($cmd, "r"));
        } else {
            $artisan = base_path('artisan');
            $cmd = "php \"{$artisan}\" taqamul:process-passenger {$passenger->id} > /dev/null 2>&1 &";
            @exec($cmd);
        }

        // Inform Telegram that retry started
        try {
            app(\App\Services\TelegramService::class)->sendMessage("🔄 <b>Auto-Retry Initiated!</b>\n━━━━━━━━━━━━━━━━━━━━\n👤 Candidate: <code>{$passenger->full_name}</code>\n🛂 Passport: <code>{$passenger->passport_number}</code>\n⚡ <i>Background bot has started re-processing...</i>");
        } catch (\Exception $e) {
            // Ignore
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Auto-retry initiated for {$passenger->full_name}!",
            ]);
        }

        return response("<!DOCTYPE html><html><head><title>Auto-Retry Started</title><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><style>body { background: #0f172a; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; text-align: center; } .card { background: #1e293b; border: 1px solid #334155; padding: 32px; border-radius: 16px; max-width: 440px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); } .icon { font-size: 48px; margin-bottom: 16px; } h2 { margin: 0 0 12px; color: #38bdf8; font-size: 22px; } p { color: #94a3b8; line-height: 1.5; margin: 0 0 20px; font-size: 14px; } .badge { background: #0284c7; color: white; padding: 6px 14px; border-radius: 9999px; font-size: 12px; font-weight: 600; display: inline-block; }</style></head><body><div class=\"card\"><div class=\"icon\">🔄</div><h2>Auto-Retry Initiated!</h2><p>Candidate <strong>" . htmlspecialchars($passenger->full_name) . "</strong> (" . htmlspecialchars($passenger->passport_number) . ") background registration engine has been restarted.</p><div class=\"badge\">Check Telegram for Live Result</div><script>setTimeout(() => { window.close(); }, 3500);</script></div></body></html>");
    }

    /**
     * Export all passengers to CSV
     */
    public function exportCsv(): StreamedResponse
    {
        $fileName = 'taqamul_passengers_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($handle, [
                'ID',
                'Full Name',
                'Passport Number',
                'National ID',
                'Date of Birth',
                'Passport Expiry',
                'Country',
                'Gender',
                'Taqamul Email',
                'Taqamul Password',
                'Temp-Mail Password',
                'Phone Number',
                'Status',
                'Created By',
                'Created At',
            ]);

            Passenger::excludePool()->with('user')->chunk(100, function ($passengers) use ($handle) {
                foreach ($passengers as $p) {
                    fputcsv($handle, [
                        $p->id,
                        $p->full_name,
                        $p->passport_number,
                        $p->national_id,
                        $p->date_of_birth ? $p->date_of_birth->format('Y-m-d') : '',
                        $p->passport_expiration_date ? $p->passport_expiration_date->format('Y-m-d') : '',
                        $p->country_name,
                        ucfirst($p->gender),
                        $p->email,
                        $p->password,
                        $p->temp_mail_password,
                        $p->country_code . ' ' . $p->phone_number,
                        ucfirst($p->status),
                        $p->user ? $p->user->name : 'System',
                        $p->created_at->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
