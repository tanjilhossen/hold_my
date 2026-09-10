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
    public static function getProxyConfig(?array $proxyInfo = null): array
    {
        $enabled = static::get('proxy_enabled', '1');
        if ($enabled !== '1' && $enabled !== 'true' && $enabled !== true) {
            return [];
        }

        return \App\Services\ProxyService::getGuzzleOptions($proxyInfo);
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

        static::saveProxyAccounts($accounts);

        // Auto-activate next available proxy from pool
        $nextActive = static::activateNextAvailableProxy();

        if ($nextActive) {
            \Illuminate\Support\Facades\Log::warning("[ProxyManager] Proxy account '{$exhaustedAccountName}' marked EXHAUSTED ({$reason}). Auto-switched to active account '{$nextActive['name']}' ({$nextActive['username']}).");
        } else {
            \Illuminate\Support\Facades\Log::error("[ProxyManager] Proxy account '{$exhaustedAccountName}' marked EXHAUSTED ({$reason}). ALERT: All proxy accounts are now EXHAUSTED in pool!");
        }

        return $nextActive;
    }

    /**
     * Mark a proxy account as temporarily cooling down after transient network drops
     * (Does NOT permanently disable the proxy!)
     */
    public static function markProxyCooling(?string $usernameOrId = null, int $seconds = 30): void
    {
        $accounts = static::getProxyAccounts();
        $coolingUntil = time() + $seconds;

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

            if ($match && ($acc['status'] ?? '') !== 'exhausted') {
                $acc['status'] = 'cooling';
                $acc['cooling_until'] = $coolingUntil;
                break;
            }
        }
        unset($acc);

        static::saveProxyAccounts($accounts);
    }

    /**
     * Activate next available proxy in pool (supports 1 to 1000+ proxies)
     */
    public static function activateNextAvailableProxy(): ?array
    {
        $accounts = static::getProxyAccounts();
        $now = time();
        $nextActive = null;

        // 1. Try first idle proxy
        foreach ($accounts as &$acc) {
            if (($acc['status'] ?? '') === 'idle') {
                $acc['status'] = 'active';
                $nextActive = $acc;
                break;
            }
        }
        unset($acc);

        // 2. If no idle proxy, check if any cooling proxy has passed cooldown
        if (!$nextActive) {
            foreach ($accounts as &$acc) {
                if (($acc['status'] ?? '') === 'cooling' && ($acc['cooling_until'] ?? 0) <= $now) {
                    $acc['status'] = 'active';
                    unset($acc['cooling_until']);
                    $nextActive = $acc;
                    break;
                }
            }
            unset($acc);
        }

        if ($nextActive) {
            // Set other active proxies to idle/cooling
            foreach ($accounts as &$acc) {
                if ($acc['id'] !== $nextActive['id'] && ($acc['status'] ?? '') === 'active') {
                    $acc['status'] = 'idle';
                }
            }
            unset($acc);

            static::saveProxyAccounts($accounts);
            static::set('proxy_host', $nextActive['host']);
            static::set('proxy_port', $nextActive['port']);
            static::set('proxy_username', $nextActive['username']);
            static::set('proxy_password', $nextActive['password']);
        }

        return $nextActive;
    }

    /**
     * Update metadata/statistics for a proxy account (bytes, IP, latency)
     */
    public static function updateProxyAccountStats(string $usernameOrId, array $stats): void
    {
        $accounts = static::getProxyAccounts();
        $updated = false;

        foreach ($accounts as &$acc) {
            if (($acc['id'] ?? '') === $usernameOrId || ($acc['username'] ?? '') === $usernameOrId) {
                foreach ($stats as $k => $v) {
                    $acc[$k] = $v;
                }
                $updated = true;
                break;
            }
        }
        unset($acc);

        if ($updated) {
            static::saveProxyAccounts($accounts);
        }
    }

    /**
     * Reactivate all proxies (resets exhausted/cooling to idle/active)
     */
    public static function reactivateAllProxies(): int
    {
        $accounts = static::getProxyAccounts();
        $count = 0;
        $first = true;

        foreach ($accounts as &$acc) {
            if ($first) {
                $acc['status'] = 'active';
                $first = false;
            } else {
                $acc['status'] = 'idle';
            }
            $acc['exhausted_at'] = null;
            $acc['exhausted_reason'] = null;
            unset($acc['cooling_until']);
            $count++;
        }
        unset($acc);

        static::saveProxyAccounts($accounts);
        if (!empty($accounts)) {
            static::activateProxyAccount($accounts[0]['id']);
        }

        return $count;
    }

    /**
     * Inspect HTTP response status/body/error and trigger auto proxy failover ONLY if data/bandwidth is truly exhausted.
     * Transient network glitches or timeouts will NEVER permanently kill the proxy.
     */
    public static function checkAndHandleProxyFailure(int $httpCode, string $body = '', string $errorMessage = ''): bool
    {
        $isTrueExhausted = \App\Services\ProxyService::isTrueBandwidthExhausted($httpCode, $body, $errorMessage);

        if ($isTrueExhausted) {
            $reason = "Bandwidth Closed: Traffic limit reached on Decodo server";
            \App\Services\ProxyService::logAuthFailure(\App\Services\ProxyService::getActiveProxy() ?? [], 'checkAndHandleProxyFailure (Quota Exhausted)', $body ?: $errorMessage);
            static::markProxyExhausted(null, $reason);
            return true;
        }

        // If it's just a transient error (e.g. timeout, connection drop, server 502/503), log transient warning and DO NOT kill the proxy!
        if ($httpCode >= 500 || str_contains($errorMessage, 'cURL error 28') || str_contains($errorMessage, 'cURL error 56') || str_contains($errorMessage, 'cURL error 7')) {
            \Illuminate\Support\Facades\Log::info("[ProxyManager ℹ️] Transient network drop detected (HTTP {$httpCode} / {$errorMessage}). Proxy remains alive in pool.");
        }

        return false;
    }
}
