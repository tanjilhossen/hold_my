<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value)
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get Guzzle / Http client proxy configuration if proxy is enabled
     */
    public static function getProxyConfig(): array
    {
        $enabled = static::get('proxy_enabled', '1');
        if ($enabled !== '1' && $enabled !== 'true' && $enabled !== true) {
            return [];
        }

        $activeAccount = static::getActiveProxyAccount();
        if ($activeAccount) {
            $host = $activeAccount['host'] ?? 'bd.decodo.com';
            $rawPort = trim($activeAccount['port'] ?? '41001');
            $user = $activeAccount['username'] ?? '';
            $pass = $activeAccount['password'] ?? '';
        } else {
            $host = static::get('proxy_host', 'bd.decodo.com');
            $rawPort = trim(static::get('proxy_port', '41001'));
            $user = static::get('proxy_username', 'spua00a572');
            $pass = static::get('proxy_password', 'o3PbblJqa5C6~vzo9M');
        }

        if (empty($host) || empty($rawPort)) {
            return [];
        }

        // Support single port (41001), port range (41001-41010), or comma list (41004,41005,41006)
        if (str_contains($rawPort, '-')) {
            $parts = explode('-', $rawPort);
            $port = rand((int)trim($parts[0]), (int)trim($parts[1]));
        } elseif (str_contains($rawPort, ',')) {
            $ports = array_map('trim', explode(',', $rawPort));
            $port = $ports[array_rand($ports)];
        } else {
            $pNum = (int)$rawPort;
            if ($pNum >= 41001 && $pNum <= 41050) {
                $port = (string)rand(41001, 41020);
            } else {
                $port = (string)$rawPort;
            }
        }

        $auth = (!empty($user) && !empty($pass)) ? "{$user}:{$pass}@" : '';
        $proxyUrl = "http://{$auth}{$host}:{$port}";

        return [
            'proxy' => $proxyUrl,
        ];
    }

    /**
     * Get all proxy accounts pool
     */
    public static function getProxyAccounts(): array
    {
        $raw = static::get('proxy_accounts', null);
        if ($raw) {
            $accounts = json_decode($raw, true);
            if (is_array($accounts) && !empty($accounts)) {
                return $accounts;
            }
        }

        // Default legacy fallback single account
        $legacyAccount = [
            'id' => 'decodo_1',
            'name' => 'Decodo Account 1',
            'host' => static::get('proxy_host', 'bd.decodo.com'),
            'port' => static::get('proxy_port', '41001'),
            'username' => static::get('proxy_username', 'spua00a572'),
            'password' => static::get('proxy_password', 'o3PbblJqa5C6~vzo9M'),
            'status' => 'active', // active, idle, exhausted
            'exhausted_at' => null,
            'exhausted_reason' => null,
            'notes' => 'Primary Decodo Proxy',
        ];

        static::set('proxy_accounts', json_encode([$legacyAccount]));
        return [$legacyAccount];
    }

    /**
     * Save proxy accounts pool
     */
    public static function saveProxyAccounts(array $accounts): void
    {
        static::set('proxy_accounts', json_encode(array_values($accounts)));
    }

    /**
     * Get the currently active proxy account from the pool
     */
    public static function getActiveProxyAccount(): ?array
    {
        $accounts = static::getProxyAccounts();
        
        foreach ($accounts as $acc) {
            if (($acc['status'] ?? '') === 'active') {
                return $acc;
            }
        }

        // If no account is active, try to auto-activate the first 'idle' account
        foreach ($accounts as &$acc) {
            if (($acc['status'] ?? '') === 'idle') {
                $acc['status'] = 'active';
                static::saveProxyAccounts($accounts);
                static::set('proxy_host', $acc['host']);
                static::set('proxy_port', $acc['port']);
                static::set('proxy_username', $acc['username']);
                static::set('proxy_password', $acc['password']);
                return $acc;
            }
        }

        return null;
    }

    /**
     * Activate a specific proxy account by ID
     */
    public static function activateProxyAccount(string $id): ?array
    {
        $accounts = static::getProxyAccounts();
        $targetAccount = null;

        foreach ($accounts as &$acc) {
            if ($acc['id'] === $id) {
                $acc['status'] = 'active';
                $acc['exhausted_at'] = null;
                $acc['exhausted_reason'] = null;
                $targetAccount = $acc;
            } else if (($acc['status'] ?? '') === 'active') {
                $acc['status'] = 'idle';
            }
        }
        unset($acc);

        if ($targetAccount) {
            static::saveProxyAccounts($accounts);
            static::set('proxy_host', $targetAccount['host']);
            static::set('proxy_port', $targetAccount['port']);
            static::set('proxy_username', $targetAccount['username']);
            static::set('proxy_password', $targetAccount['password']);
        }

        return $targetAccount;
    }

    /**
     * Mark a proxy account as EXHAUSTED (MB / Data Finished) and auto-switch to next available account
     */
    public static function markProxyExhausted(?string $usernameOrId = null, string $reason = 'Data Exhausted / MB Finished'): ?array
    {
        $accounts = static::getProxyAccounts();
        $updated = false;
        $exhaustedAccountName = '';

        foreach ($accounts as &$acc) {
            $match = false;
            if ($usernameOrId !== null) {
                if (($acc['id'] ?? '') === $usernameOrId || ($acc['username'] ?? '') === $usernameOrId) {
                    $match = true;
                }
            } else {
                if (($acc['status'] ?? '') === 'active') {
                    $match = true;
                }
            }

            if ($match) {
                $acc['status'] = 'exhausted';
                $acc['exhausted_at'] = date('Y-m-d H:i:s');
                $acc['exhausted_reason'] = $reason;
                $exhaustedAccountName = $acc['name'] ?? ($acc['username'] ?? 'Active Account');
                $updated = true;
                break;
            }
        }
        unset($acc);

        if (!$updated) {
            return null;
        }

        // Find next idle account to auto-activate
        $nextActive = null;
        foreach ($accounts as &$acc) {
            if (($acc['status'] ?? '') === 'idle') {
                $acc['status'] = 'active';
                $acc['exhausted_at'] = null;
                $acc['exhausted_reason'] = null;
                $nextActive = $acc;
                break;
            }
        }
        unset($acc);

        static::saveProxyAccounts($accounts);

        if ($nextActive) {
            static::set('proxy_host', $nextActive['host']);
            static::set('proxy_port', $nextActive['port']);
            static::set('proxy_username', $nextActive['username']);
            static::set('proxy_password', $nextActive['password']);
            \Illuminate\Support\Facades\Log::warning("[ProxyManager] Proxy account '{$exhaustedAccountName}' marked EXHAUSTED ({$reason}). Auto-switched to active account '{$nextActive['name']}' ({$nextActive['username']}).");
        } else {
            \Illuminate\Support\Facades\Log::error("[ProxyManager] Proxy account '{$exhaustedAccountName}' marked EXHAUSTED ({$reason}). ALERT: All proxy accounts are now EXHAUSTED in pool!");
        }

        return $nextActive;
    }

    /**
     * Inspect HTTP response status/body/error and trigger auto proxy failover if data/auth exhausted
     */
    public static function checkAndHandleProxyFailure(int $httpCode, string $body = '', string $errorMessage = ''): bool
    {
        $isProxyError = false;
        $reason = "HTTP {$httpCode} Proxy Error";

        if ($httpCode === 407) {
            $isProxyError = true;
            $reason = "HTTP 407 Proxy Authentication Required (Data / MB Exhausted)";
        } elseif (str_contains($errorMessage, 'Proxy') || str_contains($errorMessage, 'cURL error 56') || str_contains($errorMessage, 'cURL error 407')) {
            $isProxyError = true;
            $reason = "Proxy Network Failure: " . substr($errorMessage, 0, 100);
        } elseif (str_contains(strtolower($body), 'quota exceeded') || str_contains(strtolower($body), 'bandwidth limit') || str_contains(strtolower($body), 'proxy authentication required')) {
            $isProxyError = true;
            $reason = "Proxy Data Quota Exceeded";
        }

        if ($isProxyError) {
            static::markProxyExhausted(null, $reason);
            return true;
        }

        return false;
    }
}
