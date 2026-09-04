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
        Setting::set('slot_checker_pool_accounts', json_encode(array_values($accounts)));
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
            'email' => 'pool__485381@wafidmaster.com',
            'password' => 'Taqamul@2723!',
            'token' => null,
            'status' => 'expired (no token)',
            'role_label' => 'ONLY FOR SLOT CHECKING',
        ];
    }

    /**
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
    public function loginAndFetchTokenHttp(string $email, string $password): ?string
    {
        for ($attemptRetry = 1; $attemptRetry <= 2; $attemptRetry++) {
            try {
                $capsolverKey = Setting::get('capsolver_api_key', env('CAPSOLVER_API_KEY', 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133'));
                
                // 1. Solve reCAPTCHA v2 via CapSolver
                Log::info("[TaqamulHTTP] Solving reCAPTCHA for {$email} (Attempt {$attemptRetry}/2)...");
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

                $taskId = $createRes->json('taskId');
                if (empty($taskId)) {
                    Log::error("[TaqamulHTTP] CapSolver task creation failed: " . $createRes->body());
                    continue;
                }

                $recaptchaToken = null;
                for ($i = 0; $i < 60; $i++) {
                    usleep(400000); // 400ms
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
                    if ($resultRes->json('status') === 'failed') {
                        Log::error("[TaqamulHTTP] CapSolver failed: " . $resultRes->body());
                        break;
                    }
                }

                if (empty($recaptchaToken)) {
                    Log::error("[TaqamulHTTP] CapSolver timeout for {$email}");
                    continue;
                }

                $requestStartTime = round(microtime(true) * 1000);

                // 2. Request OTP dispatch POST /api/v1/sessions/login?locale=en
                Log::info("[TaqamulHTTP] Dispatching OTP login request for {$email}...");
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
                        'recaptcha_response' => $recaptchaToken
                    ]
                ]);

                if (!$loginRes->successful()) {
                    Log::error("[TaqamulHTTP] Login step 1 failed ({$loginRes->status()}): " . $loginRes->body());
                    continue;
                }

                $loginData = $loginRes->json();
                if (!empty($loginData['access_payload']['access'])) {
                    $token = $loginData['access_payload']['access'];
                    $this->updateAccountToken($email, $token);
                    return $token;
                }

                if (empty($loginData['required_2fa'])) {
                    Log::error("[TaqamulHTTP] 2FA not triggered: " . $loginRes->body());
                    continue;
                }

                // 3. Poll WafidMail API for fresh OTP
                Log::info("[TaqamulHTTP] Polling WafidMail for fresh OTP...");
                $wafidBaseUrl = Setting::get('wafid_mail_base_url', env('WAFID_MAIL_BASE_URL', 'https://mail.wafidmaster.com'));
                $wafidKeyId = Setting::get('wafid_mail_key_id', env('WAFID_MAIL_KEY_ID', 'ak_live_f845898cbeb87d63e21d04a6'));
                $wafidSecretKey = Setting::get('wafid_mail_secret_key', env('WAFID_MAIL_SECRET_KEY', 'sk_live_dd00dc26382465e37c31b246e37b3f345ff5c874d1ce0426'));

                $otpCode = null;
                for ($attempt = 0; $attempt < 35; $attempt++) {
                    sleep(1);
                    try {
                        $mailRes = Http::timeout(6)->withHeaders([
                            'X-API-KEY-ID' => $wafidKeyId,
                            'X-API-SECRET-KEY' => $wafidSecretKey,
                            'Accept' => 'application/json',
                        ])->get("{$wafidBaseUrl}/api/v1/messages/search", [
                            'recipient' => $email,
                            'limit' => 5
                        ]);

                        if ($mailRes->successful()) {
                            $messages = $mailRes->json('data.messages') ?? $mailRes->json('messages') ?? [];
                            foreach ($messages as $msg) {
                                $msgTime = isset($msg['received_at']) ? strtotime($msg['received_at']) * 1000 : 0;
                                if ($requestStartTime > 0 && $msgTime > 0 && $msgTime < ($requestStartTime - 5000)) {
                                    continue;
                                }
                                $body = ($msg['text_body'] ?? '') . ' ' . ($msg['subject'] ?? '') . ' ' . ($msg['html_body'] ?? '');
                                if (preg_match('/\b(\d{6})\b/', $body, $m)) {
                                    $otpCode = $m[1];
                                    break 2;
                                }
                            }
                        }
                    } catch (Exception $ex) {}
                }

                if (empty($otpCode)) {
                    Log::error("[TaqamulHTTP] OTP timeout for {$email}");
                    continue;
                }

                Log::info("[TaqamulHTTP] Found OTP {$otpCode} for {$email}. Submitting...");

                // 4. Submit OTP POST /api/v1/sessions/otp?locale=en
                $otpRes = Http::timeout(15)->withHeaders([
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
                ])->post("{$this->apiBaseUrl}/api/v1/sessions/otp?locale=en", [
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
                        Log::info("[TaqamulHTTP] Pure HTTP Login successful for {$email}!");
                        $this->updateAccountToken($email, $token);
                        return $token;
                    }
                }

                Log::error("[TaqamulHTTP] OTP submission failed for {$email}: " . $otpRes->body());
            } catch (Exception $e) {
                Log::error("[TaqamulHTTP] Exception during pure HTTP login for {$email}: " . $e->getMessage());
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
        $botScript = base_path('bot/taqamul_token_fetcher.js');
        if (!file_exists($botScript)) {
            return false;
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

        $logStreamFile = storage_path('app/bot_login_stream.log');
        @file_put_contents($logStreamFile, "[Token Bot] Launching background Fast Visual Login for: {$email}\n");

        $nodePath = 'C:\\Program Files\\nodejs\\node.exe';
        if (!file_exists($nodePath)) {
            $nodePath = trim(@shell_exec('where node 2>nul') ?: '');
            if (empty($nodePath)) $nodePath = 'node';
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $botDir = base_path('bot');
            $cmd = "cd /d \"{$botDir}\" && start \"\" \"{$nodePath}\" \"{$botScript}\" \"{$tempFile}\"";
            pclose(popen($cmd, "r"));
        } else {
            exec("\"{$nodePath}\" \"{$botScript}\" \"{$tempFile}\" > /dev/null 2>&1 &");
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
}
