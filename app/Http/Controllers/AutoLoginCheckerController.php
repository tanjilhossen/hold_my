<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TaqamulTokenService;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Exception;

class AutoLoginCheckerController extends Controller
{
    protected TaqamulTokenService $tokenService;
    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';

    public function __construct(TaqamulTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Display Auto-Login Manager Dashboard Page
     */
    public function index()
    {
        $accounts = $this->tokenService->getPoolAccounts();
        $slotChecker = $this->tokenService->getSlotCheckerAccount();
        $savedGlobalToken = trim(Setting::get('slot_checker_global_saved_token', ''));

        $totalCount = count($accounts);
        $activeCount = 0;
        $expiredCount = 0;
        $zeroCaptchaVerified = 0;
        $requiresCaptchaCount = 0;
        $untestedCount = 0;

        foreach ($accounts as &$acc) {
            $e = strtolower(trim($acc['email'] ?? ''));
            $isPool = str_starts_with($e, 'pool__');
            $acc['is_pool'] = $isPool;
            $acc['type'] = $isPool ? 'pool' : 'candidate';
            $captchaMode = $acc['captcha_mode'] ?? null;

            if ($captchaMode === 'zero_captcha') {
                $zeroCaptchaVerified++;
            } elseif ($captchaMode === 'requires_captcha') {
                $requiresCaptchaCount++;
            } else {
                $untestedCount++;
            }

            if (!empty($acc['token']) && $this->tokenService->isValidTokenFormat($acc['token'])) {
                $activeCount++;
            } else {
                $expiredCount++;
            }
        }
        unset($acc);

        $stats = [
            'total' => $totalCount,
            'active' => $activeCount,
            'expired' => $expiredCount,
            'zero_captcha_count' => $zeroCaptchaVerified,
            'requires_captcha_count' => $requiresCaptchaCount,
            'untested_count' => $untestedCount,
            'has_global_token' => !empty($savedGlobalToken),
            'global_token' => $savedGlobalToken
        ];

        return view('auto_login.index', compact('accounts', 'slotChecker', 'stats'));
    }

    /**
     * Update account captcha mode and token in pool
     */
    protected function updateAccountTag(string $email, string $captchaMode, ?string $token = null): void
    {
        $accounts = $this->tokenService->getPoolAccounts();
        $targetEmail = strtolower(trim($email));
        $updated = false;

        foreach ($accounts as &$acc) {
            if (strtolower(trim($acc['email'] ?? '')) === $targetEmail) {
                $acc['captcha_mode'] = $captchaMode;
                if ($token) {
                    $acc['token'] = $token;
                    $acc['status'] = 'active';
                    $acc['last_verified_at'] = date('Y-m-d H:i:s');
                }
                $updated = true;
                break;
            }
        }
        unset($acc);

        if ($updated) {
            $this->tokenService->savePoolAccounts($accounts);
        }
    }

    /**
     * Execute direct API login without Captcha Solver for any specific account
     */
    public function executeLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $email = trim($request->input('email'));
        $password = trim($request->input('password'));
        $forceFresh = $request->boolean('force_fresh', true);

        $logs = [];
        $logger = function ($msg) use (&$logs) {
            $logs[] = '[' . date('H:i:s') . '] ' . $msg;
        };

        $logger("Starting Direct Auto-Login for: {$email}...");

        $isPoolAccount = str_starts_with(strtolower($email), 'pool__');
        if ($isPoolAccount) {
            $logger("Account Type: ⚡ Pool Account");
        } else {
            $logger("Account Type: 👤 Passenger Candidate Account");
        }

        try {
            $token = $this->tokenService->loginAndFetchTokenHttp($email, $password, $logger, $forceFresh);

            if ($token && $this->tokenService->isValidTokenFormat($token)) {
                $logger("Bearer Token acquired successfully!");
                $this->updateAccountTag($email, 'zero_captcha', $token);

                $profileInfo = null;
                try {
                    $profRes = Http::timeout(5)->withHeaders([
                        'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
                        'X-Tenant-Name' => 'svp-international',
                        'Accept' => 'application/json',
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ])->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/profile?locale=en");

                    if ($profRes->successful()) {
                        $profileInfo = $profRes->json();
                    }
                } catch (Exception $e) {}

                return response()->json([
                    'success' => true,
                    'email' => $email,
                    'token' => $token,
                    'captcha_mode' => 'zero_captcha',
                    'profile' => $profileInfo,
                    'logs' => $logs,
                    'message' => "Successfully authenticated {$email} without captcha!"
                ]);
            }

            $failMsg = "Authentication failed.";
            $isRecaptcha = false;
            $isInvalidCreds = false;
            $logStr = implode(" ", $logs);
            if (str_contains($logStr, 'Rate Limit') || str_contains($logStr, '429')) {
                $failMsg = "Taqamul Rate Limit (429) active on this IP. Please wait 4-5 minutes before retrying.";
            } elseif (str_contains(strtolower($logStr), 'invalid email or password') || str_contains(strtolower($logStr), 'invalid login or password') || str_contains(strtolower($logStr), 'invalid credentials') || str_contains(strtolower($logStr), 'user not found') || str_contains(strtolower($logStr), 'incorrect')) {
                $isInvalidCreds = true;
                $this->updateAccountTag($email, 'invalid_credentials');
                $failMsg = "Invalid Email or Password for '{$email}'. Please edit password.";
            } elseif (str_contains($logStr, 'reCAPTCHA') || str_contains($logStr, 'recaptcha')) {
                $isRecaptcha = true;
                $this->updateAccountTag($email, 'requires_captcha');
                $failMsg = "Taqamul API returned: 'Recaptcha is not solved' for {$email}. Marked as 'Requires Captcha'.";
            }

            return response()->json([
                'success' => false,
                'email' => $email,
                'requires_captcha' => $isRecaptcha,
                'invalid_credentials' => $isInvalidCreds,
                'captcha_mode' => $isInvalidCreds ? 'invalid_credentials' : ($isRecaptcha ? 'requires_captcha' : 'unknown'),
                'logs' => $logs,
                'message' => $failMsg
            ], 422);

        } catch (Exception $e) {
            $logger("Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'email' => $email,
                'logs' => $logs,
                'message' => "Exception during authentication: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update candidate account password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($request->input('email')));
        $newPassword = trim($request->input('password'));

        $accounts = $this->tokenService->getPoolAccounts();
        $updated = false;

        foreach ($accounts as &$acc) {
            if (strtolower(trim($acc['email'] ?? '')) === $email) {
                $acc['password'] = $newPassword;
                $acc['captcha_mode'] = 'untested';
                $acc['token'] = null;
                $acc['status'] = 'expired';
                $updated = true;
                break;
            }
        }
        unset($acc);

        if ($updated) {
            $this->tokenService->savePoolAccounts($accounts);
        }

        try {
            \App\Models\Passenger::where('email', $email)->update(['password' => $newPassword]);
        } catch (Exception $e) {}

        return response()->json([
            'success' => true,
            'email' => $email,
            'password' => $newPassword,
            'message' => "Password updated successfully for {$email}!"
        ]);
    }

    /**
     * Check if a specific Bearer token is currently active on Taqamul
     */
    public function checkToken(Request $request)
    {
        $token = trim($request->input('token', ''));
        if (empty($token)) {
            return response()->json(['valid' => false, 'message' => 'Token is empty']);
        }

        try {
            $bearer = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
            $res = Http::timeout(6)->withHeaders([
                'Authorization' => $bearer,
                'X-Tenant-Name' => 'svp-international',
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/profile?locale=en");

            if ($res->successful()) {
                $user = $res->json();
                return response()->json([
                    'valid' => true,
                    'message' => 'Token is active & valid on Taqamul!',
                    'email' => $user['email'] ?? null,
                    'user' => $user
                ]);
            }

            return response()->json([
                'valid' => false,
                'status' => $res->status(),
                'message' => 'Token is expired or invalid (HTTP ' . $res->status() . ')'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Set a specific account as the Primary Slot Checker (#0)
     */
    public function setPrimary(Request $request)
    {
        $request->validate([
            'email' => 'required|string'
        ]);

        $targetEmail = strtolower(trim($request->input('email')));
        $accounts = $this->tokenService->getPoolAccounts();

        $targetAcc = null;
        $filtered = [];

        foreach ($accounts as $acc) {
            if (strtolower(trim($acc['email'] ?? '')) === $targetEmail) {
                $targetAcc = $acc;
            } else {
                $filtered[] = $acc;
            }
        }

        if (!$targetAcc) {
            return response()->json(['success' => false, 'message' => 'Account not found in pool.'], 404);
        }

        array_unshift($filtered, $targetAcc);
        $this->tokenService->savePoolAccounts($filtered);

        if (!empty($targetAcc['token'])) {
            Setting::set('slot_checker_global_saved_token', $targetAcc['token']);
        }

        return response()->json([
            'success' => true,
            'email' => $targetEmail,
            'message' => "Account {$targetEmail} is now the dedicated Slot Checker Account!"
        ]);
    }
}
