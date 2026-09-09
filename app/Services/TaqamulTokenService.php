<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Passenger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class TaqamulTokenService
{
    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';

    /**
     * Get all candidate accounts in the slot checker pool
     */
    public function getPoolAccounts(): array
    {
        $json = Setting::get('slot_checker_pool_accounts', '[]');
        $accounts = json_decode($json, true) ?: [];

        $passengerTokensLower = [];
        try {
            $passengers = Passenger::whereNotNull('token')->where('token', '!=', '')->get(['email', 'token']);
            foreach ($passengers as $p) {
                $e = strtolower(trim($p->email));
                if (!empty($e) && !empty($p->token)) {
                    $passengerTokensLower[$e] = $p->token;
                }
            }
        } catch (Exception $e) {}

        if (!empty($accounts)) {
            $updated = false;
            foreach ($accounts as &$acc) {
                $e = strtolower(trim($acc['email'] ?? ''));
                if (isset($passengerTokensLower[$e]) && !empty($passengerTokensLower[$e])) {
                    $acc['token'] = $passengerTokensLower[$e];
                    $acc['status'] = 'active';
                    $updated = true;
                }
            }
            unset($acc);
            if ($updated) {
                $this->savePoolAccounts($accounts);
            }
        } else {
            try {
                $passengers = Passenger::all();
                foreach ($passengers as $p) {
                    $e = strtolower(trim($p->email));
                    $tok = $passengerTokensLower[$e] ?? $p->token;
                    $accounts[] = [
                        'name' => $p->name ?? 'Candidate',
                        'email' => $p->email,
                        'password' => $p->password ?? 'Taqamul@2723!',
                        'token' => $tok,
                        'status' => !empty($tok) ? 'active' : 'expired',
                    ];
                }
            } catch (Exception $e) {}
        }

        return $accounts;
    }

    /**
     * Save the updated pool of candidate accounts
     */
    public function savePoolAccounts(array $accounts): void
    {
        $json = json_encode(array_values($accounts), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Setting::set('slot_checker_pool_accounts', $json);
        $jsonPath = database_path('pool_accounts.json');
        if (file_exists($jsonPath)) {
            @file_put_contents($jsonPath, $json);
        }
    }

    /**
     * Get dedicated Slot Checking Account (Index #0 in candidate pool)
     */
    public function getSlotCheckerAccount(): array
    {
        $accounts = $this->getPoolAccounts();
        if (!empty($accounts)) {
            $checkerAcc = $accounts[0];
            $checkerAcc['role_label'] = 'ONLY FOR SLOT CHECKING';
            return $checkerAcc;
        }

        return [
            'name' => 'Slot Checker Account',
            'email' => 'pool__259939@wafidmaster.com',
            'password' => 'Taqamul@4642!',
            'token' => null,
            'status' => 'expired (no token)',
            'role_label' => 'ONLY FOR SLOT CHECKING',
        ];
    }

    /**
     * Get active Bearer token for a specific candidate account email
     */
    public function getTokenForAccount(string $email): ?string
    {
        $email = strtolower(trim($email));

        // 1. Check Passenger model
        try {
            $passenger = Passenger::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($passenger && $this->isValidTokenFormat($passenger->token)) {
                return $passenger->token;
            }
        } catch (Exception $e) {}

        // 2. Check Candidate Pool Accounts
        $accounts = $this->getPoolAccounts();
        foreach ($accounts as $acc) {
            if (strtolower(trim($acc['email'] ?? '')) === $email) {
                if ($this->isValidTokenFormat($acc['token'] ?? null)) {
                    return $acc['token'];
                }
            }
        }

        return null;
    }

    /**
     * Get account password for a specific candidate account email
     */
    public function getPasswordForAccount(string $email): ?string
    {
        $email = strtolower(trim($email));
        $accounts = $this->getPoolAccounts();
        foreach ($accounts as $acc) {
            if (strtolower(trim($acc['email'] ?? '')) === $email) {
                if (!empty($acc['password'])) {
                    return $acc['password'];
                }
            }
        }

        try {
            $passenger = Passenger::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($passenger && !empty($passenger->password)) {
                return $passenger->password;
            }
        } catch (Exception $e) {}

        return 'Taqamul@2723!';
    }


    /**
     * Validate token string format to prevent invalid JSON strings
     */
    public function isValidTokenFormat(?string $token): bool
    {
        if (empty($token)) return false;
        $t = trim(str_replace('Bearer ', '', $token));
        if (str_starts_with($t, '{') || str_starts_with($t, '[') || str_contains($t, 'portal') || str_contains($t, '"')) {
            return false;
        }
        return strlen($t) > 20;
    }

    /**
     * Get dedicated Slot Checker Token ONLY (Persisted default token)
     */
    public function getSlotCheckerToken(): ?string
    {
        // 1. Static manual backup token
        $manualToken = trim(Setting::get('slot_checker_manual_token', ''));
        if ($this->isValidTokenFormat($manualToken)) {
            return $manualToken;
        }

        // 2. Persistent global saved slot checker token
        $globalSaved = trim(Setting::get('slot_checker_global_saved_token', ''));
        if ($this->isValidTokenFormat($globalSaved)) {
            return $globalSaved;
        }

        // 3. Check Index #0 Account in candidate pool
        $checkerAcc = $this->getSlotCheckerAccount();
        $token = $checkerAcc['token'] ?? null;
        if ($this->isValidTokenFormat($token)) {
            return $token;
        }

        // 4. Check any valid token in candidate pool
        $accounts = $this->getPoolAccounts();
        foreach ($accounts as $acc) {
            if ($this->isValidTokenFormat($acc['token'] ?? null)) {
                return $acc['token'];
            }
        }

        return null;
    }

    /**
     * Get single persistent Slot Checker token (Round-Robin disabled for slot checking)
     */
    public function getValidRoundRobinToken(): ?string
    {
        // STRICT RULE: Always reuse the persistent Slot Checker Token for all slot checking requests
        return $this->getSlotCheckerToken();
    }

    /**
     * Get up to $count valid pool account tokens for multi-account slot holding (Excludes Slot Checker Account)
     */
    public function getValidPoolAccountTokens(int $count = 10, bool $autoFetchMissing = true): array
    {
        $checkerAcc = $this->getSlotCheckerAccount();
        $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__485381@wafidmaster.com'));

        $accounts = $this->getPoolAccounts();
        $validAccounts = [];

        foreach ($accounts as $acc) {
            if (count($validAccounts) >= $count) break;

            $email = strtolower(trim($acc['email'] ?? ''));
            // STRICT RULE: Dedicated Slot Checker Account MUST NOT be used for slot locking!
            if ($email === $checkerEmail) continue;

            $token = $acc['token'] ?? null;
            if ($this->isValidTokenFormat($token)) {
                $validAccounts[] = [
                    'email' => $acc['email'],
                    'password' => $acc['password'] ?? 'Taqamul@2723!',
                    'token' => $token,
                ];
            }
        }

        if ($autoFetchMissing && count($validAccounts) < $count) {
            foreach ($accounts as $acc) {
                if (count($validAccounts) >= $count) break;

                $email = strtolower(trim($acc['email'] ?? ''));
                if (empty($email) || $email === $checkerEmail) continue;

                $already = false;
                foreach ($validAccounts as $v) {
                    if (strtolower($v['email']) === $email) {
                        $already = true;
                        break;
                    }
                }
                if ($already) continue;

                $newToken = $this->loginAndFetchToken($email, $acc['password'] ?? 'Taqamul@2723!');
                if (!empty($newToken) && $this->isValidTokenFormat($newToken)) {
                    $validAccounts[] = [
                        'email' => $email,
                        'password' => $acc['password'] ?? 'Taqamul@2723!',
                        'token' => $newToken,
                    ];
                }
            }
        }

        return $validAccounts;
    }

    /**
     * Mark a specific token as expired/401 and trigger auto-login ONLY IF 401 response comes from Taqamul API
     */
    public function markTokenExpired(string $badToken): void
    {
        Setting::set('slot_checker_global_saved_token', '');

        $accounts = $this->getPoolAccounts();
        $expiredEmail = null;
        $expiredPassword = null;

        foreach ($accounts as &$acc) {
            if (!empty($badToken) && ($acc['token'] ?? '') === $badToken) {
                $acc['token'] = null;
                $acc['status'] = 'expired (401)';
                if (!$expiredEmail && !empty($acc['email'])) {
                    $expiredEmail = $acc['email'];
                    $expiredPassword = $acc['password'] ?? null;
                }
            }
        }
        $this->savePoolAccounts($accounts);

        // Auto-login ON DEMAND only when explicit non-empty 401 Expired token received
        if ($expiredEmail && $expiredPassword && !empty($badToken)) {
            Log::warning("[TaqamulTokenService] Token expired for {$expiredEmail}. Launching fresh login...");
            $this->startLoginBotAsync($expiredEmail, $expiredPassword);
        }
    }

    /**
     * Validate whether a Bearer token is currently active on Taqamul
     */
    public function validateToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            $bearer = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
            $res = Http::timeout(6)->withHeaders([
                'Authorization' => $bearer,
                'X-Tenant-Name' => 'svp-international',
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/profile", ['locale' => 'en']);

            if ($res->successful()) {
                return true;
            }

            // 401 / 403 means expired
            return false;
        } catch (Exception $e) {
            // Temporary network glitch - consider existing non-empty token valid to prevent popup loops
            return true;
        }
    }

    /**
     * Update token for candidate account in pool and database
     */
    public function updateAccountToken(string $email, string $token): void
    {
        $accounts = $this->getPoolAccounts();
        $targetEmail = strtolower(trim($email));
        $updated = false;

        foreach ($accounts as &$acc) {
            if (strtolower(trim($acc['email'] ?? '')) === $targetEmail) {
                $acc['token'] = $token;
                $acc['status'] = 'active';
                $acc['last_verified_at'] = date('Y-m-d H:i:s');
                $updated = true;
            }
        }
        unset($acc);

        if ($updated) {
            $this->savePoolAccounts($accounts);
        }

        try {
            Passenger::where('email', $email)->update(['token' => $token]);
        } catch (Exception $e) {}
    }

    /**
     * Direct fast pure HTTP login in PHP without launching browser
     */
    public function loginAndFetchTokenHttp(string $email, string $password, ?callable $logger = null, bool $forceFresh = false): ?string
    {
        $logStep = function ($msg) use ($logger) {
            Log::info("[TaqamulHTTP] {$msg}");
            if ($logger) {
                $logger($msg);
            }
        };

        // 0. Quick probe check (if not forcing fresh login): If candidate already has an active, valid Bearer token, reuse it!
        if (!$forceFresh) {
            $existingToken = $this->getTokenForAccount($email);
            if (!empty($existingToken) && $this->isValidTokenFormat($existingToken)) {
                try {
                    $proxyCfg = Setting::getProxyConfig();
                    $opts = [
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => 0,
                        ]
                    ];
                    if (!empty($proxyCfg['proxy'])) {
                        $opts['proxy'] = $proxyCfg['proxy'];
                    }

                    $probeRes = Http::withoutVerifying()->timeout(5)->withOptions($opts)->withHeaders([
                        'Accept' => 'application/json',
                        'X-Tenant-Name' => 'svp-international',
                        'Authorization' => str_starts_with($existingToken, 'Bearer ') ? $existingToken : "Bearer {$existingToken}",
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    ])->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en");

                    if ($probeRes->status() !== 401 && !str_contains($probeRes->body(), 'Signature has expired')) {
                        $logStep("[Token Bot 🔑] Existing token is active & valid! Reusing token for {$email}.");
                        $this->updateAccountToken($email, $existingToken);
                        return $existingToken;
                    }
                } catch (Exception $e) {}
            }
        }

        for ($attemptRetry = 1; $attemptRetry <= 2; $attemptRetry++) {
            try {
                $requestStartTime = round(microtime(true) * 1000);
                $recaptchaToken = '';

                $proxyCfg = Setting::getProxyConfig();
                $opts = [
                    'curl' => [
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0,
                    ]
                ];
                $directOpts = $opts;
                if (!empty($proxyCfg['proxy']) && $attemptRetry === 1) {
                    $opts['proxy'] = $proxyCfg['proxy'];
                }

                // 1. Send Direct Captcha-Free Login request to Taqamul API (recaptcha_response: "")
                $logStep("[Token Bot 🚀] Dispatching Direct Captcha-Free OTP login request to Taqamul API...");
                $loginHeaders = [
                    'Host' => 'svp-international-api.pacc.sa',
                    'X-Tenant-Name' => 'svp-international',
                    'Sec-Ch-Ua-Platform' => '"Windows"',
                    'Cache-Control' => 'no-cache',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Pragma' => 'no-cache',
                    'Sec-Ch-Ua' => '"Not-A.Brand";v="24", "Chromium";v="146"',
                    'Sec-Ch-Ua-Mobile' => '?0',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
                    'Accept' => 'application/json, text/plain, */*',
                    'Content-Type' => 'application/json',
                    'Origin' => 'https://svp-international.pacc.sa',
                    'Sec-Fetch-Site' => 'same-site',
                    'Sec-Fetch-Mode' => 'cors',
                    'Sec-Fetch-Dest' => 'empty',
                    'Referer' => 'https://svp-international.pacc.sa/'
                ];
                $loginPayload = [
                    'user' => [
                        'login' => $email,
                        'password' => $password,
                        'otp_method' => 'email',
                        'fe_app' => 'legislator',
                        'recaptcha_response' => ''
                    ]
                ];

                $loginRes = null;
                try {
                    $loginRes = Http::withoutVerifying()->timeout(15)->withHeaders($loginHeaders)->withOptions($opts)->post("{$this->apiBaseUrl}/api/v1/sessions/login?locale=en", $loginPayload);
                    if ($loginRes) {
                        Setting::checkAndHandleProxyFailure($loginRes->status(), $loginRes->body());
                    }
                } catch (\Exception $e) {
                    Setting::checkAndHandleProxyFailure(0, '', $e->getMessage());
                    if (isset($opts['proxy'])) {
                        $logStep("[Token Bot ⚠️] Proxy login failed (" . $e->getMessage() . "). Retrying login directly without proxy...");
                        $loginRes = Http::withoutVerifying()->timeout(15)->withHeaders($loginHeaders)->withOptions($directOpts)->post("{$this->apiBaseUrl}/api/v1/sessions/login?locale=en", $loginPayload);
                    } else {
                        throw $e;
                    }
                }

                if ($loginRes && isset($opts['proxy']) && $loginRes->status() >= 500) {
                    $logStep("[Token Bot ⚠️] Proxy login returned HTTP {$loginRes->status()}. Retrying login directly without proxy...");
                    $loginRes = Http::withoutVerifying()->timeout(15)->withHeaders($loginHeaders)->withOptions($directOpts)->post("{$this->apiBaseUrl}/api/v1/sessions/login?locale=en", $loginPayload);
                }

                // Fallback to CapSolver/2Captcha only if Taqamul explicitly requires recaptcha_response
                if (!$loginRes->successful() && str_contains(strtolower($loginRes->body()), 'recaptcha')) {
                    $logStep("[Token Bot 🛡️] Captcha required by Taqamul. Solving via Captcha API...");
                    $capsolverKey = Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133'));
                    $twoCaptchaKey = Setting::get('twocaptcha_key', env('TWOCAPTCHA_KEY', ''));

                    if (!empty($twoCaptchaKey)) {
                        $logStep("[2Captcha API ⚡] Solving reCAPTCHA via 2Captcha API for {$email}...");
                        $recaptchaToken = $this->solveRecaptchaTwoCaptcha($twoCaptchaKey);
                    }

                    if (empty($recaptchaToken) && !empty($capsolverKey)) {
                        $logStep("[CapSolver AI ⚡] Solving reCAPTCHA v2 via CapSolver AI (Attempt {$attemptRetry}/2)...");
                        $createRes = Http::timeout(15)->withOptions([
                            'curl' => [
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_SSL_VERIFYPEER => false,
                            ]
                        ])->post('https://api.capsolver.com/createTask', [
                            'clientKey' => $capsolverKey,
                            'task' => [
                                'type' => 'ReCaptchaV2TaskProxyLess',
                                'websiteURL' => 'https://svp-international.pacc.sa/auth/login?role=labor',
                                'websiteKey' => '6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy',
                            ]
                        ]);

                        $createJson = $createRes->json() ?: [];
                        $taskId = $createJson['taskId'] ?? null;
                        if (!empty($taskId)) {
                            for ($i = 0; $i < 60; $i++) {
                                usleep(400000);
                                $resultRes = Http::timeout(15)->withOptions([
                                    'curl' => [
                                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                        CURLOPT_SSL_VERIFYPEER => false,
                                    ]
                                ])->post('https://api.capsolver.com/getTaskResult', [
                                    'clientKey' => $capsolverKey,
                                    'taskId' => $taskId
                                ]);

                                if ($resultRes->json('status') === 'ready') {
                                    $recaptchaToken = $resultRes->json('solution.gRecaptchaResponse');
                                    break;
                                }
                                if ($resultRes->json('status') === 'failed') break;
                            }
                        }
                    }

                    if (!empty($recaptchaToken)) {
                        $loginRes = Http::timeout(15)->withHeaders([
                            'Host' => 'svp-international-api.pacc.sa',
                            'X-Tenant-Name' => 'svp-international',
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json, text/plain, */*',
                            'Origin' => 'https://svp-international.pacc.sa',
                            'Referer' => 'https://svp-international.pacc.sa/',
                            'Connection' => 'close',
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                        ])->withOptions([
                            'curl' => [
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_SSL_VERIFYHOST => 0,
                            ]
                        ])->post("{$this->apiBaseUrl}/api/v1/sessions/login?locale=en", [
                            'user' => [
                                'login' => $email,
                                'password' => $password,
                                'otp_method' => 'email',
                                'fe_app' => 'legislator',
                                'recaptcha_response' => $recaptchaToken
                            ]
                        ]);
                    }
                }

                if (!$loginRes->successful()) {
                    if ($loginRes->status() === 429 || str_contains($loginRes->body(), 'Rate Limit')) {
                        $logStep("[Token Bot ⚠️] Taqamul Login Rate Limit (429) hit for {$email}. Checking if existing token works...");
                        if (!empty($existingToken) && $this->isValidTokenFormat($existingToken)) {
                            $logStep("[Token Bot 🔑] Reusing active saved token for {$email} despite rate limit.");
                            $this->updateAccountToken($email, $existingToken);
                            return $existingToken;
                        }
                        $logStep("[Token Bot ⏳] Rate Limit active. Waiting 5 seconds before retry...");
                        sleep(5);
                    } elseif (str_contains($loginRes->body(), 'recaptcha')) {
                        $logStep("[Token Bot ⚠️] Taqamul API requires reCAPTCHA for '{$email}'. Checking solver fallback...");
                        
                        $twoCaptchaKey = Setting::get('twocaptcha_key', env('TWOCAPTCHA_KEY', ''));
                        $capsolverKey = Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', ''));
                        $solvedToken = null;

                        if (!empty($twoCaptchaKey)) {
                            $logStep("[2Captcha API ⚡] Solving reCAPTCHA via 2Captcha...");
                            $solvedToken = $this->solveRecaptchaTwoCaptcha($twoCaptchaKey);
                        } elseif (!empty($capsolverKey)) {
                            $logStep("[CapSolver AI ⚡] Solving reCAPTCHA via CapSolver...");
                            $solvedToken = $this->solveRecaptchaCapSolver($capsolverKey);
                        }

                        if (!empty($solvedToken)) {
                            $logStep("[Token Bot 🚀] reCAPTCHA solved! Retrying login with token...");
                            $loginRes = Http::timeout(15)->withHeaders([
                                'Host' => 'svp-international-api.pacc.sa',
                                'X-Tenant-Name' => 'svp-international',
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json, text/plain, */*',
                                'Origin' => 'https://svp-international.pacc.sa',
                                'Referer' => 'https://svp-international.pacc.sa/',
                                'Connection' => 'close',
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'
                            ])->withOptions([
                                'curl' => [
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_SSL_VERIFYPEER => false,
                                    CURLOPT_SSL_VERIFYHOST => 0,
                                ]
                            ])->post("{$this->apiBaseUrl}/api/v1/sessions/login?locale=en", [
                                'user' => [
                                    'login' => $email,
                                    'password' => $password,
                                    'otp_method' => 'email',
                                    'fe_app' => 'legislator',
                                    'recaptcha_response' => $solvedToken
                                ]
                            ]);

                            if ($loginRes->successful()) {
                                // proceed to OTP verification below
                            } else {
                                $logStep("[Token Bot ❌] Retry with captcha failed ({$loginRes->status()}): " . $loginRes->body());
                                continue;
                            }
                        } else {
                            $logStep("[Token Bot ℹ️] NOTE: Taqamul server returned 'Recaptcha is not solved' for '{$email}'. Please use zero-captcha accounts like pool__259939 or pool__780330, or configure a funded solver key.");
                            break;
                        }
                    } else {
                        $logStep("[Token Bot ❌] Login step 1 failed ({$loginRes->status()}): " . $loginRes->body());
                    }

                    if (!$loginRes->successful()) {
                        continue;
                    }
                }

                $loginData = $loginRes->json();
                if (!empty($loginData['access_payload']['access'])) {
                    $token = $loginData['access_payload']['access'];
                    $this->updateAccountToken($email, $token);
                    $logStep("[Token Bot 🔑] Direct login successful without 2FA!");
                    return $token;
                }

                if (empty($loginData['required_2fa'])) {
                    $logStep("[Token Bot ❌] 2FA not triggered: " . $loginRes->body());
                    continue;
                }

                // 3. Poll WafidMail API for fresh OTP
                $logStep("[WafidMail API ✉️] Login OTP dispatched! Polling WafidMail inbox for fresh OTP...");
                $wafidMailService = app(WafidMailService::class);
                $otpCode = $wafidMailService->waitForLatestOtp($email, 45, $logger, $requestStartTime);

                if (empty($otpCode)) {
                    $logStep("[WafidMail API ⚠️] OTP email timeout for {$email}. Retrying...");
                    continue;
                }

                $logStep("[WafidMail API 📩] Found OTP code: {$otpCode}! Submitting to Taqamul API...");

                // 4. Submit OTP POST /api/v1/sessions/otp?locale=en
                $otpRes = Http::withoutVerifying()->timeout(15)->withHeaders([
                    'Host' => 'svp-international-api.pacc.sa',
                    'X-Tenant-Name' => 'svp-international',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/plain, */*',
                    'Origin' => 'https://svp-international.pacc.sa',
                    'Referer' => 'https://svp-international.pacc.sa/',
                    'Connection' => 'close',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'
                ])->withOptions($opts)->post("{$this->apiBaseUrl}/api/v1/sessions/otp?locale=en", [
                    'user' => [
                        'login' => $email,
                        'password' => $password,
                        'otp_attempt' => (string)$otpCode,
                        'fe_app' => 'legislator',
                        'otp_method' => 'email'
                    ]
                ]);

                if ($otpRes->successful()) {
                    $token = $otpRes->json('access_payload.access');
                    if ($this->isValidTokenFormat($token)) {
                        $logStep("[Token Bot 🔑] Pure HTTP Login successful for {$email}!");
                        $this->updateAccountToken($email, $token);
                        return $token;
                    }
                }

                $logStep("[Token Bot ❌] OTP submission failed: " . $otpRes->body());
            } catch (Exception $e) {
                $logStep("[Token Bot ❌] Exception during login: " . $e->getMessage());
            }
        }
        return null;
    }

    /**
     * Perform background login to get a fresh Bearer token
     */
    public function loginAndFetchToken(string $email, string $password): ?string
    {
        // ⚡ Try ultra-fast PHP pure HTTP login first (< 10s)
        $httpToken = $this->loginAndFetchTokenHttp($email, $password);
        if (!empty($httpToken) && $this->isValidTokenFormat($httpToken)) {
            return $httpToken;
        }

        if (function_exists('session') && session()->isStarted()) {
            session()->save();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $botScript = base_path('bot/taqamul_token_fetcher.js');
        if (!file_exists($botScript)) {
            return null;
        }

        $config = [
            'email' => $email,
            'password' => $password,
            'capsolver_api_key' => Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', '')),
            'wafid_mail_base_url' => Setting::get('wafid_mail_base_url', env('WAFID_MAIL_BASE_URL', 'https://mail.wafidmaster.com')),
            'wafid_mail_key_id' => Setting::get('wafid_mail_key_id', env('WAFID_MAIL_KEY_ID', '')),
            'wafid_mail_secret_key' => Setting::get('wafid_mail_secret_key', env('WAFID_MAIL_SECRET_KEY', '')),
        ];

        $tempFile = storage_path('app/temp_token_cfg_' . uniqid() . '.json');
        file_put_contents($tempFile, json_encode($config));

        if (function_exists('session') && session()->isStarted()) {
            session()->save();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            // OS-aware node path resolution for Windows and Linux/Ubuntu VPS
            $nodePath = PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\nodejs\\node.exe' : '/usr/bin/node';
            if (!file_exists($nodePath)) {
                $whichCmd = PHP_OS_FAMILY === 'Windows' ? 'where node 2>nul' : 'which node 2>/dev/null';
                $nodePath = trim(shell_exec($whichCmd) ?: '');
                if (empty($nodePath)) $nodePath = 'node';
            }

            $cmd = PHP_OS_FAMILY === 'Windows' 
                ? "\"{$nodePath}\" \"{$botScript}\" \"{$tempFile}\""
                : "{$nodePath} {$botScript} {$tempFile}";

            $output = shell_exec($cmd . ' 2>&1');
            Log::info('[BotLogin] Raw output: ' . substr($output ?? 'NULL', 0, 1000));

            if ($output && preg_match('/FINAL_TOKEN_RESULT:(.*)/', $output, $matches)) {
                $result = json_decode(trim($matches[1]), true);
                if (!empty($result['success']) && !empty($result['token'])) {
                    @unlink($tempFile);
                    return $result['token'];
                } else {
                    Log::warning('[BotLogin] Token result failed: ' . json_encode($result));
                }
            } else {
                Log::warning('[BotLogin] No FINAL_TOKEN_RESULT found in output');
            }
        } catch (Exception $e) {
            Log::error("Taqamul Auto-Login Token Error: " . $e->getMessage());
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        return null;
    }

    /**
     * Launch background async login process to fetch Bearer token without blocking HTTP requests
     */
    public function startLoginBotAsync(string $email, string $password): bool
    {
        $logStreamFile = storage_path('app/bot_login_stream.log');
        @file_put_contents($logStreamFile, "[Token Bot] Launching pure HTTP API Login for: {$email}...\n");

        $phpPath = (defined('PHP_BINARY') && file_exists(PHP_BINARY))
            ? PHP_BINARY
            : (PHP_OS_FAMILY === 'Windows' ? 'C:\\Users\\MD Tanjil Hpssen\\AppData\\Local\\Microsoft\\WinGet\\Packages\\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\\php.exe' : '/usr/bin/php');
        if (!file_exists($phpPath)) {
            $whichCmd = PHP_OS_FAMILY === 'Windows' ? 'where php 2>nul' : 'which php 2>/dev/null';
            $phpPath = trim(shell_exec($whichCmd) ?: 'php');
        }

        $artisanPath = base_path('artisan');
        $escEmail = escapeshellarg($email);
        $escPass = base64_encode($password);

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "start \"\" /B \"{$phpPath}\" -d extension=pdo_sqlite \"{$artisanPath}\" taqamul:fast-login {$escEmail} {$escPass}";
            pclose(popen($cmd, "r"));
        } else {
            exec("{$phpPath} -d extension=pdo_sqlite {$artisanPath} taqamul:fast-login {$escEmail} {$escPass} > /dev/null 2>&1 &");
        }

        return true;
    }

    /**
     * Send light heartbeat profile pings for active candidate accounts to keep sessions active
     */
    public function keepAlivePoolTokens(): array
    {
        $accounts = $this->getPoolAccounts();
        if (empty($accounts)) {
            return ['total' => 0, 'active' => 0, 'expired' => 0];
        }

        // Parallel concurrent heartbeat pings across pool accounts
        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($accounts) {
            $calls = [];
            foreach ($accounts as $idx => $acc) {
                $token = $acc['token'] ?? null;
                if (!empty($token)) {
                    $bearer = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
                    $calls[$idx] = $pool->withHeaders([
                        'Authorization' => $bearer,
                        'X-Tenant-Name' => 'svp-international',
                        'Accept' => 'application/json',
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ])->timeout(6)->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/profile", ['locale' => 'en']);
                }
            }
            return $calls;
        });

        $activeCount = 0;
        $expiredCount = 0;
        $updated = false;

        foreach ($accounts as $idx => &$acc) {
            $token = $acc['token'] ?? null;
            if (empty($token)) {
                $acc['status'] = 'expired (no token)';
                $expiredCount++;
                $updated = true;
                continue;
            }

            $res = $responses[$idx] ?? null;
            if ($res instanceof \Illuminate\Http\Client\Response && $res->successful()) {
                $acc['status'] = 'active';
                $acc['last_verified_at'] = date('Y-m-d H:i:s');
                $activeCount++;
            } else {
                $acc['token'] = null;
                $acc['status'] = 'expired (401)';
                $expiredCount++;
                $updated = true;
            }
        }

        if ($updated) {
            $this->savePoolAccounts($accounts);
        }

        return [
            'total' => count($accounts),
            'active' => $activeCount,
            'expired' => $expiredCount,
        ];
    }

    /**
     * Launch background auto-logins for any candidate accounts marked expired or missing tokens
     */
    public function refreshExpiredPoolTokens(int $maxBatch = 3): array
    {
        $accounts = $this->getPoolAccounts();
        $triggered = 0;

        foreach ($accounts as $acc) {
            if ($triggered >= $maxBatch) break;

            $token = $acc['token'] ?? null;
            $status = $acc['status'] ?? '';
            $email = $acc['email'] ?? '';
            $password = $acc['password'] ?? '';

            if (empty($token) || str_contains($status, 'expired')) {
                if (!empty($email) && !empty($password)) {
                    $this->startLoginBotAsync($email, $password);
                    $triggered++;
                }
            }
        }

        return [
            'triggered_logins' => $triggered,
            'message' => "Triggered {$triggered} background auto-logins for expired pool accounts."
        ];
    }

    /**
     * Solve Google reCAPTCHA v2 using 2Captcha API
     */
    public function solveRecaptchaTwoCaptcha(string $apiKey): ?string
    {
        try {
            $siteKey = '6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy';
            $pageUrl = 'https://svp-international.pacc.sa/auth/login?role=labor';

            $createRes = Http::timeout(15)->get("https://2captcha.com/in.php", [
                'key' => $apiKey,
                'method' => 'userrecaptcha',
                'googlekey' => $siteKey,
                'pageurl' => $pageUrl,
                'json' => 1
            ]);

            if (!$createRes->successful() || $createRes->json('status') !== 1) {
                Log::error("[2Captcha] Failed to create task: " . $createRes->body());
                return null;
            }

            $taskId = $createRes->json('request');
            Log::info("[2Captcha] Task created with ID: {$taskId}. Waiting for solution...");

            for ($i = 0; $i < 30; $i++) {
                sleep(2);
                $resRes = Http::timeout(10)->get("https://2captcha.com/res.php", [
                    'key' => $apiKey,
                    'action' => 'get',
                    'id' => $taskId,
                    'json' => 1
                ]);

                if ($resRes->successful() && $resRes->json('status') === 1) {
                    return $resRes->json('request');
                }

                if ($resRes->json('request') !== 'CAPCHA_NOT_READY') {
                    Log::error("[2Captcha] Error getting result: " . $resRes->body());
                    break;
                }
            }
        } catch (Exception $e) {
            Log::error("[2Captcha] Exception: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Solve Google reCAPTCHA v2 using CapSolver API
     */
    public function solveRecaptchaCapSolver(string $apiKey): ?string
    {
        try {
            $siteKey = '6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy';
            $pageUrl = 'https://svp-international.pacc.sa/auth/login?role=labor';

            $createRes = Http::timeout(15)->post('https://api.capsolver.com/createTask', [
                'clientKey' => $apiKey,
                'task' => [
                    'type' => 'ReCaptchaV2TaskProxyLess',
                    'websiteURL' => $pageUrl,
                    'websiteKey' => $siteKey,
                ]
            ]);

            $createJson = $createRes->json() ?: [];
            $taskId = $createJson['taskId'] ?? null;
            if (empty($taskId)) {
                Log::warning("[CapSolver] Task creation failed: " . ($createJson['errorDescription'] ?? $createRes->body()));
                return null;
            }

            for ($i = 0; $i < 60; $i++) {
                usleep(500000); // 500ms
                $resultRes = Http::timeout(10)->post('https://api.capsolver.com/getTaskResult', [
                    'clientKey' => $apiKey,
                    'taskId' => $taskId
                ]);

                if ($resultRes->json('status') === 'ready') {
                    return $resultRes->json('solution.gRecaptchaResponse');
                }
                if ($resultRes->json('status') === 'failed') {
                    Log::warning("[CapSolver] Task failed: " . ($resultRes->json('errorDescription') ?? $resultRes->body()));
                    break;
                }
            }
        } catch (Exception $e) {
            Log::error("[CapSolver] Exception: " . $e->getMessage());
        }

        return null;
    }
}
