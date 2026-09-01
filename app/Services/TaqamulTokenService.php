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
     * Get up to $count valid pool account tokens for multi-account slot holding
     */
    public function getValidPoolAccountTokens(int $count = 10): array
    {
        $accounts = $this->getPoolAccounts();
        $validAccounts = [];

        foreach ($accounts as $acc) {
            if (count($validAccounts) >= $count) break;

            $token = $acc['token'] ?? null;
            if ($this->isValidTokenFormat($token)) {
                $validAccounts[] = [
                    'email' => $acc['email'],
                    'password' => $acc['password'] ?? 'Taqamul@2723!',
                    'token' => $token,
                ];
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
     * Perform background login to get a fresh Bearer token
     */
    public function loginAndFetchToken(string $email, string $password): ?string
    {
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
            // Use full node path to work under Apache (which may not inherit system PATH)
            $nodePath = 'C:\\Program Files\\nodejs\\node.exe';
            if (!file_exists($nodePath)) {
                $nodePath = trim(shell_exec('where node 2>nul') ?: '');
                if (empty($nodePath)) $nodePath = 'node';
            }

            $cmd = "\"{$nodePath}\" \"{$botScript}\" \"{$tempFile}\"";
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
