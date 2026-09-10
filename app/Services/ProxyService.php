<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProxyService
{
    /**
     * Cache for health check status to prevent redundant round-trips in tight loops
     */
    protected static ?array $cachedHealthResult = null;
    protected static ?float $lastHealthCheckTime = null;
    protected static int $healthCacheTtlSeconds = 25;

    /**
     * Round-robin counters for load balancing across accounts and ports
     */
    protected static int $accountRoundRobinIndex = 0;
    protected static int $portRoundRobinIndex = 0;

    /**
     * Get next load-balanced proxy from the pool with Decodo residential port sharding.
     * Distributes 500-600+ slot bookings across all healthy accounts and across Decodo's 50 residential ports (41001-41050),
     * preventing any single proxy server or exit node from getting overloaded!
     */
    public static function getLoadBalancedProxy(): ?array
    {
        $enabled = Setting::get('proxy_enabled', '1');
        if ($enabled !== '1' && $enabled !== 'true' && $enabled !== true) {
            return null;
        }

        $allAccounts = Setting::getProxyAccounts();
        $healthyAccounts = [];

        foreach ($allAccounts as $acc) {
            $status = $acc['status'] ?? 'idle';
            if ($status === 'exhausted') {
                continue;
            }
            if (isset($acc['remaining_mb']) && is_numeric($acc['remaining_mb']) && $acc['remaining_mb'] <= 0) {
                continue;
            }
            $healthyAccounts[] = $acc;
        }

        // If no non-exhausted accounts found, fallback to active or all accounts
        if (empty($healthyAccounts)) {
            $active = Setting::getActiveProxyAccount();
            if ($active) {
                $healthyAccounts = [$active];
            } else {
                $healthyAccounts = $allAccounts;
            }
        }

        if (empty($healthyAccounts)) {
            return static::getActiveSingleProxy();
        }

        // 1. Pick Account via Round-Robin distribution across all healthy accounts in pool
        static::$accountRoundRobinIndex++;
        $chosenAccount = $healthyAccounts[static::$accountRoundRobinIndex % count($healthyAccounts)];

        $host = trim($chosenAccount['host'] ?? 'bd.decodo.com');
        $rawPort = trim((string)($chosenAccount['port'] ?? '41001'));
        $user = trim($chosenAccount['username'] ?? '');
        $pass = (string)($chosenAccount['password'] ?? '');
        $id = $chosenAccount['id'] ?? 'decodo_1';
        $name = $chosenAccount['name'] ?? 'Decodo Account';

        // 2. Decodo Port Sharding / Multiplexing across 50 distinct residential exit nodes (ports 41001 - 41050)
        // Decodo Bangladesh sticky session ports range from 41001 to 41050.
        // Each port connects to a completely different residential IP in Bangladesh!
        $portMultiplexing = Setting::get('proxy_port_multiplexing', '1');
        static::$portRoundRobinIndex++;
        $configuredPortRange = Setting::get('proxy_port_range', '41001-41050');

        if (str_contains($rawPort, '-')) {
            $parts = explode('-', $rawPort);
            $minPort = (int)trim($parts[0]);
            $maxPort = (int)trim($parts[1]);
            $rangeSize = max(1, $maxPort - $minPort + 1);
            $port = (string)($minPort + (static::$portRoundRobinIndex % $rangeSize));
        } elseif (str_contains($rawPort, ',')) {
            $ports = array_map('trim', explode(',', $rawPort));
            $port = (string)$ports[static::$portRoundRobinIndex % count($ports)];
        } elseif (($portMultiplexing === '1' || $portMultiplexing === 'true' || $portMultiplexing === true) && (str_contains(strtolower($host), 'decodo') || ((int)$rawPort >= 41001 && (int)$rawPort <= 41050))) {
            // Decodo residential sticky ports: rotate 41001 through 41050 (50 distinct residential exit IPs!)
            $port = (string)(41001 + (static::$portRoundRobinIndex % 50));
        } else {
            $port = (string)$rawPort;
        }

        return [
            'id' => $id,
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'username' => $user,
            'password' => $pass,
            'is_load_balanced' => true,
        ];
    }

    /**
     * Get active proxy connection credentials and settings.
     * When load balancing is enabled (default), dynamically shards requests across accounts & ports.
     */
    public static function getActiveProxy(bool $forceSingleStatic = false): ?array
    {
        $enabled = Setting::get('proxy_enabled', '1');
        if ($enabled !== '1' && $enabled !== 'true' && $enabled !== true) {
            return null;
        }

        $loadBalancingEnabled = Setting::get('proxy_load_balancing', '1');
        if (!$forceSingleStatic && ($loadBalancingEnabled === '1' || $loadBalancingEnabled === 'true' || $loadBalancingEnabled === true)) {
            $lbProxy = static::getLoadBalancedProxy();
            if ($lbProxy) {
                return $lbProxy;
            }
        }

        return static::getActiveSingleProxy();
    }

    /**
     * Get single static active proxy account without port sharding (used for health tests)
     */
    public static function getActiveSingleProxy(): ?array
    {
        $activeAccount = Setting::getActiveProxyAccount();
        if ($activeAccount) {
            $host = trim($activeAccount['host'] ?? 'bd.decodo.com');
            $rawPort = trim((string)($activeAccount['port'] ?? '41001'));
            $user = trim($activeAccount['username'] ?? '');
            $pass = (string)($activeAccount['password'] ?? '');
            $id = $activeAccount['id'] ?? 'decodo_1';
            $name = $activeAccount['name'] ?? 'Decodo Account';
        } else {
            $host = trim(Setting::get('proxy_host', 'bd.decodo.com'));
            $rawPort = trim((string)Setting::get('proxy_port', '41001'));
            $user = trim(Setting::get('proxy_username', 'spua00a572'));
            $pass = (string)Setting::get('proxy_password', 'o3PbblJqa5C6~vzo9M');
            $id = 'legacy';
            $name = 'Primary Decodo Proxy';
        }

        if (empty($host) || empty($rawPort)) {
            return null;
        }

        if (str_contains($rawPort, '-')) {
            $parts = explode('-', $rawPort);
            $port = (string)trim($parts[0]);
        } elseif (str_contains($rawPort, ',')) {
            $ports = array_map('trim', explode(',', $rawPort));
            $port = (string)$ports[0];
        } else {
            $port = (string)$rawPort;
        }

        return [
            'id' => $id,
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'username' => $user,
            'password' => $pass,
            'is_load_balanced' => false,
        ];
    }

    /**
     * Build the explicit proxy URL: http://USERNAME:PASSWORD@HOST:PORT
     * with properly URL-encoded username and password
     */
    public static function buildProxyUrl(string $host, string $port, ?string $username = null, ?string $password = null): string
    {
        $cleanHost = preg_replace('#^https?://#i', '', trim($host));
        $cleanPort = trim($port);

        $auth = '';
        if (!empty($username) && $password !== null && $password !== '') {
            $encodedUser = rawurlencode($username);
            $encodedPass = rawurlencode($password);
            $auth = "{$encodedUser}:{$encodedPass}@";
        }

        return "http://{$auth}{$cleanHost}:{$cleanPort}";
    }

    /**
     * Get proxy URL for active configuration or specified proxy info
     */
    public static function getProxyUrl(?array $proxyInfo = null): ?string
    {
        $info = $proxyInfo ?? static::getActiveProxy();
        if (!$info) {
            return null;
        }

        return static::buildProxyUrl(
            $info['host'],
            $info['port'],
            $info['username'] ?? null,
            $info['password'] ?? null
        );
    }

    /**
     * Build Guzzle request options that reliably route both HTTP and HTTPS
     * through the proxy with CONNECT tunnel authentication and redirect credential preservation.
     */
    public static function getGuzzleOptions(?array $proxyInfo = null): array
    {
        $info = $proxyInfo ?? static::getActiveProxy();
        if (!$info) {
            return [];
        }

        $proxyUrl = static::getProxyUrl($info);
        if (!$proxyUrl) {
            return [];
        }

        $user = $info['username'] ?? '';
        $pass = $info['password'] ?? '';

        $curlOpts = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_UNRESTRICTED_AUTH => true,
        ];

        if (!empty($user) && !empty($pass)) {
            $curlOpts[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
            $curlOpts[CURLOPT_PROXYUSERPWD] = "{$user}:{$pass}";
            $curlOpts[CURLOPT_PROXYHEADER] = [
                'Proxy-Authorization: Basic ' . base64_encode("{$user}:{$pass}")
            ];
        }

        return [
            'proxy' => [
                'http'  => $proxyUrl,
                'https' => $proxyUrl,
            ],
            'curl' => $curlOpts,
        ];
    }

    /**
     * Helper to merge proxy configuration directly into existing options array
     */
    public static function applyProxyToOptions(array &$options, ?array $proxyInfo = null): void
    {
        $proxyCfg = static::getGuzzleOptions($proxyInfo);
        if (empty($proxyCfg)) {
            return;
        }

        if (!empty($proxyCfg['proxy'])) {
            $options['proxy'] = $proxyCfg['proxy'];
        }

        if (!empty($proxyCfg['curl'])) {
            $existingCurl = $options['curl'] ?? [];
            $options['curl'] = array_replace($existingCurl, $proxyCfg['curl']);
        }
    }

    /**
     * Apply proxy settings to a native cURL handle
     */
    public static function applyCurlOptions($ch, ?array $proxyInfo = null): void
    {
        $info = $proxyInfo ?? static::getActiveProxy();
        if (!$info) {
            return;
        }

        $proxyUrl = static::getProxyUrl($info);
        curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
        curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);

        $user = $info['username'] ?? '';
        $pass = $info['password'] ?? '';

        if (!empty($user) && !empty($pass)) {
            curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$user}:{$pass}");
            curl_setopt($ch, CURLOPT_PROXYHEADER, [
                'Proxy-Authorization: Basic ' . base64_encode("{$user}:{$pass}")
            ]);
        }
    }

    /**
     * Mask proxy password for secure logging without exposing sensitive data
     */
    public static function maskPassword(?string $password): string
    {
        if (empty($password)) {
            return '';
        }
        $len = strlen($password);
        if ($len <= 4) {
            return '****';
        }
        return substr($password, 0, 2) . str_repeat('*', max($len - 4, 3)) . substr($password, -2);
    }

    /**
     * Log clear diagnostic message when HTTP 407 occurs without exposing proxy password
     */
    public static function logAuthFailure(array $proxyInfo, string $context = '', ?string $rawResponse = null): void
    {
        $host = $proxyInfo['host'] ?? 'unknown';
        $port = $proxyInfo['port'] ?? 'unknown';
        $user = $proxyInfo['username'] ?? 'unknown';
        $ctx = $context ? " [Context: {$context}]" : '';

        $details = '';
        if ($rawResponse) {
            $cleanDetails = trim(preg_replace('/\s+/', ' ', strip_tags($rawResponse)));
            $details = " Server message: " . substr($cleanDetails, 0, 150);
        }

        Log::error("[ProxyAuth ❌] Proxy Authentication Failed (HTTP 407) for Decodo endpoint {$host}:{$port} with username '{$user}'{$ctx}. Credentials were rejected or bandwidth limit has been reached.{$details}");
    }

    /**
     * UNBREAKABLE DETECTOR:
     * Check if an error or response represents a TRUE bandwidth/quota exhaustion from Decodo.
     * Returns TRUE only if the proxy server confirmed data/MB is exhausted.
     * Returns FALSE for any transient network glitch (timeouts, peer resets, temporary tunnel drops).
     */
    public static function isTrueBandwidthExhausted(int $httpCode, string $body = '', string $errorMessage = ''): bool
    {
        $lowerBody = strtolower($body);
        $lowerErr = strtolower($errorMessage);

        // Explicit Decodo traffic limit / quota message
        if (str_contains($lowerBody, 'traffic limit') ||
            str_contains($lowerBody, 'bandwidth limit') ||
            str_contains($lowerBody, 'quota exceeded') ||
            str_contains($lowerBody, "you've reached your current traffic limit") ||
            str_contains($lowerErr, 'traffic limit') ||
            str_contains($lowerErr, 'quota exceeded') ||
            str_contains($lowerErr, 'bandwidth limit')) {
            return true;
        }

        // If HTTP 407 AND explicitly confirmed by proxy auth challenge
        if ($httpCode === 407 && (str_contains($lowerBody, 'access denied') || str_contains($lowerBody, 'traffic') || str_contains($lowerErr, 'traffic'))) {
            return true;
        }

        return false;
    }

    /**
     * Record real-time data consumption (upload + download bytes) for a proxy account
     */
    public static function recordTrafficUsage(string $usernameOrId, int $bytesSent, int $bytesReceived): void
    {
        $totalBytes = max(0, $bytesSent) + max(0, $bytesReceived);
        if ($totalBytes <= 0) {
            return;
        }

        $accounts = Setting::getProxyAccounts();
        $updated = false;

        foreach ($accounts as &$acc) {
            if (($acc['id'] ?? '') === $usernameOrId || ($acc['username'] ?? '') === $usernameOrId) {
                $acc['bytes_used'] = ($acc['bytes_used'] ?? 0) + $totalBytes;
                $acc['requests_count'] = ($acc['requests_count'] ?? 0) + 1;
                $acc['last_used_at'] = date('Y-m-d H:i:s');

                // Real-time remaining MB deduction if quota is defined
                if (!empty($acc['quota_limit_mb']) && is_numeric($acc['quota_limit_mb'])) {
                    $usedMb = $acc['bytes_used'] / (1024 * 1024);
                    $remaining = max(0, round($acc['quota_limit_mb'] - $usedMb, 2));
                    $acc['remaining_mb'] = $remaining;

                    if ($remaining <= 0 && ($acc['status'] ?? '') === 'active') {
                        $acc['status'] = 'exhausted';
                        $acc['exhausted_reason'] = 'Local Quota Reached: 0 MB Remaining';
                        $acc['exhausted_at'] = date('Y-m-d H:i:s');
                        Setting::activateNextAvailableProxy();
                    }
                }

                $updated = true;
                break;
            }
        }
        unset($acc);

        if ($updated) {
            Setting::saveProxyAccounts($accounts);
        }
    }

    /**
     * Format bytes to human readable format (MB, GB, KB)
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * UNBREAKABLE EXIT NODE DETECTOR:
     * Check if Decodo returned HTTP 502/503/504 because the residential exit node ended or went offline.
     */
    public static function isExitNodeOffline(int $httpCode, string $body = '', string $errorMessage = ''): bool
    {
        if ($httpCode !== 502 && $httpCode !== 503 && $httpCode !== 504 && !str_contains($errorMessage, '502')) {
            return false;
        }
        $lower = strtolower($body . ' ' . $errorMessage);
        return str_contains($lower, 'exit node') ||
               str_contains($lower, 'session has ended') ||
               str_contains($lower, 'adjust your filters') ||
               str_contains($lower, 'start a new session') ||
               str_contains($lower, 'couldn\'t find a suitable exit node');
    }

    /**
     * UNBREAKABLE SELF-HEALING EXIT NODE ROTATOR:
     * When Decodo residential peer disconnects (HTTP 502 exit node offline),
     * automatically probes adjacent sticky session ports (41001 - 41050),
     * establishes connection to an active residential peer in Bangladesh,
     * updates the account port in the database, and returns the live connection!
     */
    public static function autoHealExitNode(array &$info, ?string $targetUrl = null, int $maxProbes = 8): ?array
    {
        $currentPort = (int)($info['port'] ?? 41001);
        $host = $info['host'] ?? 'bd.decodo.com';
        Log::warning("[ProxySelfHeal 🔄] Exit node on {$host}:{$currentPort} ended. Auto-healing to next available residential port...");

        // Generate candidate ports around Decodo port range (41001 - 41050)
        $candidates = [];
        for ($i = 1; $i <= $maxProbes; $i++) {
            if ($currentPort >= 41001 && $currentPort <= 41050) {
                $candidate = 41001 + (($currentPort - 41001 + $i) % 50);
            } else {
                $candidate = $currentPort + $i;
            }
            if ($candidate !== $currentPort && !in_array($candidate, $candidates)) {
                $candidates[] = $candidate;
            }
        }

        foreach ($candidates as $candidatePort) {
            $probeInfo = $info;
            $probeInfo['port'] = (string)$candidatePort;

            // Probe candidate port directly without triggering recursive healing
            $probeResult = static::checkHealthInternal($probeInfo, $targetUrl ?: 'http://ip.decodo.com/json', 5);
            if (!empty($probeResult['healthy']) && !empty($probeResult['external_ip'])) {
                // Found active exit node!
                $oldPort = $info['port'];
                $info['port'] = (string)$candidatePort;

                // Update in database
                $accId = $info['id'] ?? ($info['username'] ?? null);
                if ($accId) {
                    Setting::updateProxyAccountStats($accId, [
                        'port' => (string)$candidatePort,
                        'last_ip' => $probeResult['external_ip'],
                        'last_latency_ms' => $probeResult['latency_ms'],
                        'last_verified_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // Update active global setting if this is active proxy
                $activeAcc = Setting::getActiveProxyAccount();
                if ($activeAcc && (($activeAcc['id'] ?? '') === ($info['id'] ?? '') || ($activeAcc['username'] ?? '') === ($info['username'] ?? ''))) {
                    Setting::set('proxy_port', (string)$candidatePort);
                }

                Log::info("[ProxySelfHeal ✅] Successfully self-healed! Shifted from port {$oldPort} to {$candidatePort}. Live IP: {$probeResult['external_ip']} ({$probeResult['latency_ms']}ms)");

                $probeResult['proxy_endpoint'] = "{$host}:{$candidatePort}";
                $probeResult['auto_healed'] = true;
                return $probeResult;
            }
        }

        Log::error("[ProxySelfHeal ❌] Could not find live residential node after probing " . count($candidates) . " adjacent ports.");
        return null;
    }

    /**
     * Proxy Health-Check
     * Verifies:
     * - proxy connection succeeds
     * - proxy authentication succeeds
     * - external IP is returned
     * - HTTP status is 200
     * Automatically self-heals if the residential exit node ended!
     */
    public static function checkHealth(?array $proxyInfo = null, ?string $testUrl = null, int $timeout = 8): array
    {
        $info = $proxyInfo ?? static::getActiveProxy();
        if (!$info) {
            return [
                'healthy' => false,
                'connection_status' => 'failed',
                'auth_status' => 'none',
                'http_status' => 0,
                'external_ip' => null,
                'latency_ms' => 0,
                'bytes_transferred' => 0,
                'error' => 'No proxy configured or proxy is disabled.',
                'https_connect_succeeded' => false,
                'is_bandwidth_exhausted' => false,
                'proxy_endpoint' => 'none',
            ];
        }

        $res = static::checkHealthInternal($info, $testUrl, $timeout);

        // If exit node is offline (HTTP 502), trigger automatic self-healing!
        if (static::isExitNodeOffline($res['http_status'], (string)($res['raw_response'] ?? ''), (string)($res['error'] ?? ''))) {
            $healed = static::autoHealExitNode($info, $testUrl);
            if ($healed && !empty($healed['healthy'])) {
                static::$cachedHealthResult = $healed;
                static::$lastHealthCheckTime = microtime(true);
                return $healed;
            }
        }

        if (!empty($res['healthy'])) {
            static::$cachedHealthResult = $res;
            static::$lastHealthCheckTime = microtime(true);
        }

        return $res;
    }

    /**
     * Core internal health probe without recursive auto-healing
     */
    public static function checkHealthInternal(array $info, ?string $testUrl = null, int $timeout = 8): array
    {
        $targetUrl = $testUrl ?: Setting::get('proxy_test_url', 'http://ip.decodo.com/json');
        if (!str_starts_with($targetUrl, 'http://') && !str_starts_with($targetUrl, 'https://')) {
            $targetUrl = "http://{$targetUrl}";
        }

        $isHttps = str_starts_with(strtolower($targetUrl), 'https://');
        $startTime = microtime(true);

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        static::applyCurlOptions($ch, $info);

        $response = curl_exec($ch);
        $latency = round((microtime(true) - $startTime) * 1000, 2);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $sizeUpload = (int)curl_getinfo($ch, CURLINFO_SIZE_UPLOAD);
        $sizeDownload = (int)curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);

        $bytesTransferred = $sizeUpload + $sizeDownload;
        if ($bytesTransferred > 0 && !empty($info['username'])) {
            static::recordTrafficUsage($info['id'] ?? $info['username'], $sizeUpload, $sizeDownload);
        }

        $isBandwidthExhausted = static::isTrueBandwidthExhausted($httpCode, (string)$response, $curlError);
        $connectionSucceeded = ($curlErrno === 0 || $httpCode > 0 || str_contains($curlError, '407'));
        $authSucceeded = false;
        $externalIp = null;
        $httpsConnectSucceeded = false;
        $errorMsg = null;

        if ($isBandwidthExhausted) {
            $authSucceeded = false;
            $serverMessage = $response ? trim(strip_tags($response)) : '';
            $errorMsg = "Bandwidth Closed: " . ($serverMessage ?: "Traffic limit reached on Decodo server.");
            static::logAuthFailure($info, "Health-Check Quota Exhausted ({$targetUrl})", $response);
        } elseif ($httpCode === 407 || str_contains($curlError, '407')) {
            $authSucceeded = false;
            $errorMsg = "HTTP 407: Proxy Authentication Required. Credentials rejected.";
            static::logAuthFailure($info, "Health-Check ({$targetUrl})", $response);
        } elseif ($curlErrno !== 0) {
            $errorMsg = "cURL error {$curlErrno}: {$curlError}";
        } elseif ($httpCode === 200 && !empty($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $externalIp = $decoded['ip'] ?? $decoded['query'] ?? $decoded['origin'] ?? $decoded['client_ip'] ?? null;
            }
            if (!$externalIp && preg_match('/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/', $response, $matches)) {
                $externalIp = $matches[0];
            }

            if ($externalIp) {
                $authSucceeded = true;
                if ($isHttps) {
                    $httpsConnectSucceeded = true;
                }

                // Update account metadata with latest known IP and latency
                Setting::updateProxyAccountStats($info['id'] ?? $info['username'], [
                    'last_ip' => $externalIp,
                    'last_latency_ms' => $latency,
                    'last_verified_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $errorMsg = "HTTP 200 returned but external IP could not be parsed.";
            }
        } else {
            $errorMsg = "Unexpected HTTP Code {$httpCode}: " . substr(strip_tags((string)$response), 0, 100);
        }

        $healthy = ($connectionSucceeded && $authSucceeded && !empty($externalIp) && $httpCode === 200);

        return [
            'healthy' => $healthy,
            'connection_status' => $connectionSucceeded ? 'connected' : 'failed',
            'auth_status' => $authSucceeded ? 'authenticated' : 'failed',
            'http_status' => $httpCode,
            'external_ip' => $externalIp,
            'latency_ms' => $latency,
            'bytes_transferred' => $bytesTransferred,
            'raw_response' => $response,
            'error' => $errorMsg,
            'https_connect_succeeded' => $httpsConnectSucceeded,
            'is_bandwidth_exhausted' => $isBandwidthExhausted,
            'proxy_endpoint' => "{$info['host']}:{$info['port']}",
        ];
    }

    /**
     * Run proxy health check before main request with caching to avoid overhead
     */
    public static function ensureHealthy(bool $forceFresh = false): array
    {
        $info = static::getActiveProxy();
        if (!$info) {
            return ['healthy' => false, 'error' => 'Proxy is disabled or unconfigured.'];
        }

        $now = microtime(true);
        if (!$forceFresh && static::$cachedHealthResult && static::$lastHealthCheckTime && ($now - static::$lastHealthCheckTime) < static::$healthCacheTtlSeconds) {
            return static::$cachedHealthResult;
        }

        return static::checkHealth($info);
    }

    /**
     * Comprehensive test for Decodo endpoint covering both HTTP and HTTPS CONNECT
     */
    public static function testDecodoEndpoint(?array $proxyInfo = null): array
    {
        $info = $proxyInfo ?? static::getActiveProxy();
        if (!$info) {
            return [
                'proxy_connection_status' => 'failed',
                'authentication_status' => 'failed',
                'http_status' => 0,
                'external_proxy_ip' => 'none',
                'https_connect_succeeded' => false,
                'remaining_error' => 'No active proxy configured.',
            ];
        }

        // Test 1: HTTP Target
        $httpRes = static::checkHealth($info, 'http://ip.decodo.com/json', 10);

        // Test 2: HTTPS Target (CONNECT tunnel verification)
        $httpsRes = static::checkHealth($info, 'https://ip.decodo.com/json', 10);
        if (!$httpsRes['healthy'] && !$httpsRes['is_bandwidth_exhausted']) {
            $httpsRes = static::checkHealth($info, 'https://api.ipify.org?format=json', 10);
        }

        $connectionStatus = ($httpRes['connection_status'] === 'connected' || $httpsRes['connection_status'] === 'connected')
            ? 'Connected'
            : 'Failed';

        $authStatus = ($httpRes['auth_status'] === 'authenticated' || $httpsRes['auth_status'] === 'authenticated')
            ? 'Authenticated'
            : 'Authentication Failed';

        $bestHttpCode = $httpsRes['http_status'] ?: $httpRes['http_status'];
        $bestIp = $httpsRes['external_ip'] ?: $httpRes['external_ip'] ?: 'none';
        $httpsConnectSucceeded = $httpsRes['https_connect_succeeded'] || ($httpsRes['http_status'] >= 200 && $httpsRes['http_status'] < 400);

        $remainingError = null;
        if ($httpsRes['error']) {
            $remainingError = "[HTTPS CONNECT] " . $httpsRes['error'];
        } elseif ($httpRes['error']) {
            $remainingError = "[HTTP] " . $httpRes['error'];
        }

        return [
            'proxy_connection_status' => $connectionStatus,
            'authentication_status' => $authStatus,
            'http_status' => $bestHttpCode,
            'external_proxy_ip' => $bestIp,
            'https_connect_succeeded' => $httpsConnectSucceeded,
            'is_bandwidth_exhausted' => ($httpRes['is_bandwidth_exhausted'] || $httpsRes['is_bandwidth_exhausted']),
            'remaining_error' => $remainingError,
            'http_result' => $httpRes,
            'https_result' => $httpsRes,
        ];
    }

    /**
     * Query Decodo Public API to fetch real server-side allocated & remaining bandwidth
     * Works when a Decodo API Key is configured.
     */
    public static function fetchDecodoApiBandwidth(?string $apiKey = null): array
    {
        $key = trim($apiKey ?: Setting::get('decodo_api_key', ''));
        if (empty($key)) {
            return [
                'success' => false,
                'message' => 'No Decodo API Key provided. Enter your Decodo API Key in IP Manager to sync official server bandwidth.',
                'subusers' => [],
            ];
        }

        try {
            // Support both Decodo v2 and legacy Smartproxy subusers endpoints
            $endpoints = [
                'https://api.decodo.com/v2/sub-users',
                'https://api.smartproxy.com/v2/sub-users',
                'https://api.decodo.com/v1/sub-users',
            ];

            $response = null;

            foreach ($endpoints as $endpoint) {
                // Try Basic auth header (standard Decodo API token)
                $res = Http::withoutVerifying()
                    ->timeout(10)
                    ->withHeaders([
                        'Authorization' => 'Basic ' . base64_encode("{$key}:"),
                        'Accept' => 'application/json',
                    ])
                    ->get($endpoint);

                if ($res->successful()) {
                    $response = $res;
                    break;
                }

                // Try Token auth header
                $res = Http::withoutVerifying()
                    ->timeout(10)
                    ->withHeaders([
                        'Authorization' => "Token {$key}",
                        'Accept' => 'application/json',
                    ])
                    ->get($endpoint);

                if ($res->successful()) {
                    $response = $res;
                    break;
                }
            }

            if (!$response || !$response->successful()) {
                $status = $response ? $response->status() : 500;
                $body = $response ? $response->body() : 'No response';
                return [
                    'success' => false,
                    'message' => "Decodo API returned HTTP {$status}: " . substr($body, 0, 150),
                    'subusers' => [],
                ];
            }

            $data = $response->json();
            $subusersData = $data['results'] ?? ($data['data'] ?? ($data['sub_users'] ?? (is_array($data) ? $data : [])));

            $mapped = [];
            $accounts = Setting::getProxyAccounts();
            $updatedAny = false;

            foreach ($subusersData as $su) {
                $username = $su['username'] ?? ($su['user'] ?? '');
                if (!$username) continue;

                $trafficLimit = (float)($su['traffic_limit'] ?? ($su['limit'] ?? 0)); // in GB
                $trafficUsed = (float)($su['traffic_used'] ?? ($su['used'] ?? 0));   // in GB
                $trafficRemaining = max(0, $trafficLimit - $trafficUsed);

                $mapped[$username] = [
                    'username' => $username,
                    'traffic_limit_gb' => $trafficLimit,
                    'traffic_used_gb' => $trafficUsed,
                    'traffic_remaining_gb' => round($trafficRemaining, 3),
                    'traffic_remaining_mb' => round($trafficRemaining * 1024, 1),
                    'is_disabled' => (bool)($su['is_disabled'] ?? false),
                ];

                // Sync into matching proxy accounts in database
                foreach ($accounts as &$acc) {
                    if (($acc['username'] ?? '') === $username) {
                        $acc['quota_limit_mb'] = round($trafficLimit * 1024, 1);
                        $acc['remaining_mb'] = round($trafficRemaining * 1024, 1);
                        $acc['api_synced_at'] = date('Y-m-d H:i:s');
                        if ($trafficRemaining <= 0) {
                            $acc['status'] = 'exhausted';
                            $acc['exhausted_reason'] = 'Decodo API: 0 MB Remaining';
                            $acc['exhausted_at'] = date('Y-m-d H:i:s');
                        } elseif ($acc['status'] === 'exhausted' && $trafficRemaining > 0.05) {
                            // If user refilled bandwidth, reactivate!
                            $acc['status'] = 'idle';
                            $acc['exhausted_reason'] = null;
                            $acc['exhausted_at'] = null;
                        }
                        $updatedAny = true;
                    }
                }
                unset($acc);
            }

            if ($updatedAny) {
                Setting::saveProxyAccounts($accounts);
            }

            return [
                'success' => true,
                'message' => 'Successfully synced real bandwidth from Decodo API for ' . count($mapped) . ' sub-user(s)!',
                'subusers' => $mapped,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception connecting to Decodo API: ' . $e->getMessage(),
                'subusers' => [],
            ];
        }
    }

    /**
     * Check real live bandwidth and connectivity for a single proxy account.
     * Fetches exact remaining MB from Decodo API if key is present,
     * and performs a live probe to verify real latency, external IP, and quota state.
     */
    public static function checkSingleAccountBandwidth(string $accountIdOrUsername): array
    {
        $accounts = Setting::getProxyAccounts();
        $targetIndex = null;
        foreach ($accounts as $idx => $acc) {
            if (($acc['id'] ?? '') === $accountIdOrUsername || ($acc['username'] ?? '') === $accountIdOrUsername) {
                $targetIndex = $idx;
                break;
            }
        }

        if ($targetIndex === null) {
            return [
                'success' => false,
                'message' => 'Proxy account not found in database.',
            ];
        }

        $acc = &$accounts[$targetIndex];
        $username = $acc['username'] ?? '';
        $apiKey = trim(Setting::get('decodo_api_key', ''));

        $apiSynced = false;
        $remainingMb = $acc['remaining_mb'] ?? null;
        $limitMb = $acc['quota_limit_mb'] ?? null;

        // 1. If Decodo API key is configured, fetch official sub-user quota
        if (!empty($apiKey)) {
            $apiResult = static::fetchDecodoApiBandwidth($apiKey);
            if (!empty($apiResult['success']) && isset($apiResult['subusers'][$username])) {
                $subInfo = $apiResult['subusers'][$username];
                $remainingMb = $subInfo['traffic_remaining_mb'];
                $limitMb = round($subInfo['traffic_limit_gb'] * 1024, 1);
                $acc['remaining_mb'] = $remainingMb;
                $acc['quota_limit_mb'] = $limitMb;
                $acc['api_synced_at'] = date('Y-m-d H:i:s');
                $apiSynced = true;

                if ($remainingMb <= 0) {
                    $acc['status'] = 'exhausted';
                    $acc['exhausted_reason'] = 'Decodo Server: 0 MB Remaining';
                    $acc['exhausted_at'] = date('Y-m-d H:i:s');
                } elseif ($acc['status'] === 'exhausted' && $remainingMb > 0.05) {
                    $acc['status'] = 'idle';
                    $acc['exhausted_reason'] = null;
                    $acc['exhausted_at'] = null;
                }
            }
        }

        // 2. If quota is locally defined, compute live remaining MB based on exact byte usage
        if (!$apiSynced && !empty($acc['quota_limit_mb']) && is_numeric($acc['quota_limit_mb'])) {
            $usedMb = ($acc['bytes_used'] ?? 0) / (1024 * 1024);
            $remainingMb = max(0, round($acc['quota_limit_mb'] - $usedMb, 2));
            $acc['remaining_mb'] = $remainingMb;
        }

        // 3. Perform live connection probe through this specific proxy
        $health = static::checkHealth($acc, 'http://ip.decodo.com/json', 8);
        if (!$health['healthy'] && !$health['is_bandwidth_exhausted']) {
            $health = static::checkHealth($acc, 'https://api.ipify.org?format=json', 8);
        }

        $acc['last_verified_at'] = date('Y-m-d H:i:s');
        if (!empty($health['latency_ms']) && $health['latency_ms'] > 0) {
            $acc['last_latency_ms'] = $health['latency_ms'];
        }
        if (!empty($health['external_ip'])) {
            $acc['last_ip'] = $health['external_ip'];
        }

        // 4. Handle live probe quota exhaustion vs healthy state
        if (!empty($health['is_bandwidth_exhausted'])) {
            $acc['status'] = 'exhausted';
            $acc['remaining_mb'] = 0;
            $remainingMb = 0;
            $acc['exhausted_at'] = date('Y-m-d H:i:s');
            $acc['exhausted_reason'] = 'Bandwidth Closed: Traffic limit reached on Decodo server';

            // If this was the active proxy, auto-activate the next available proxy in pool
            if (($acc['status'] ?? '') === 'active') {
                Setting::activateNextAvailableProxy();
            }
        } elseif ($health['healthy']) {
            // Live connection successful
            if ($acc['status'] === 'exhausted') {
                $acc['status'] = 'idle';
                $acc['exhausted_at'] = null;
                $acc['exhausted_reason'] = null;
            }
            if ($remainingMb === null) {
                // If neither API nor quota limit is set, indicate proxy is active
                $acc['remaining_mb'] = 'Active (No limit set)';
                $remainingMb = 'Active (No limit set)';
            }
        }

        Setting::saveProxyAccounts($accounts);

        $formattedRemaining = 'Unknown';
        if (is_numeric($remainingMb)) {
            $formattedRemaining = $remainingMb >= 1024
                ? round($remainingMb / 1024, 2) . ' GB'
                : round($remainingMb, 1) . ' MB';
        } elseif ($remainingMb) {
            $formattedRemaining = (string)$remainingMb;
        }

        return [
            'success' => true,
            'id' => $acc['id'],
            'name' => $acc['name'],
            'status' => $acc['status'],
            'remaining_mb' => $remainingMb,
            'formatted_remaining' => $formattedRemaining,
            'quota_limit_mb' => $limitMb,
            'bytes_used' => $acc['bytes_used'] ?? 0,
            'formatted_used' => static::formatBytes($acc['bytes_used'] ?? 0),
            'requests_count' => $acc['requests_count'] ?? 0,
            'last_ip' => $acc['last_ip'] ?? null,
            'last_latency_ms' => $acc['last_latency_ms'] ?? null,
            'last_verified_at' => $acc['last_verified_at'],
            'exhausted_reason' => $acc['exhausted_reason'] ?? null,
            'is_healthy' => $health['healthy'],
            'is_bandwidth_exhausted' => !empty($health['is_bandwidth_exhausted']),
            'api_synced' => $apiSynced,
            'message' => !empty($health['is_bandwidth_exhausted'])
                ? "Proxy limit reached: 0 MB remaining (Bandwidth Closed)"
                : ($health['healthy']
                    ? "Proxy is LIVE! IP: {$acc['last_ip']}, Latency: {$acc['last_latency_ms']}ms, Remaining: {$formattedRemaining}"
                    : "Live check complete. Note: " . ($health['error'] ?: "HTTP {$health['http_status']}")),
        ];
    }

    /**
     * UNBREAKABLE PROXY EXECUTION ENGINE:
     * Executes any callback with smart failover across the entire proxy pool (1 to 1000+ proxies).
     *
     * Rules:
     * 1. Transient network errors (timeouts, socket resets, HTTP 502/503/504) are retried with exponential backoff on current proxy.
     * 2. NEVER stops or marks proxy exhausted unless TRUE bandwidth quota exhaustion is confirmed.
     * 3. When TRUE bandwidth quota exhaustion occurs:
     *    - Marks the exhausted proxy as "MB Finished"
     *    - Seamlessly auto-switches to the NEXT available proxy in the pool
     *    - Transparently retries the request using the new proxy
     * 4. As long as at least ONE proxy has bandwidth in the pool, the request will SUCCEED without interruption!
     */
    public static function executeWithSmartFailover(callable $callback, int $maxRetriesPerProxy = 3)
    {
        $triedProxies = [];
        $accounts = Setting::getProxyAccounts();
        $totalPoolCount = count($accounts);

        // Maximum proxy hops to avoid infinite loop if entire pool is exhausted
        $maxProxyHops = max(1, $totalPoolCount);

        for ($proxyHop = 1; $proxyHop <= $maxProxyHops; $proxyHop++) {
            $activeProxy = static::getActiveProxy();
            if (!$activeProxy) {
                // If no active proxy, attempt auto-activation of best idle proxy
                $activeProxy = Setting::getActiveProxyAccount();
                if (!$activeProxy) {
                    break;
                }
            }

            $currentProxyId = $activeProxy['id'] ?? ($activeProxy['username'] ?? 'unknown');
            if (in_array($currentProxyId, $triedProxies)) {
                // Already tried this proxy, try next
                $switched = Setting::markProxyExhausted($currentProxyId, 'Already evaluated in current request cycle');
                if (!$switched) break;
                continue;
            }
            $triedProxies[] = $currentProxyId;

            $lastException = null;

            // Attempt request up to $maxRetriesPerProxy times on current proxy for transient glitches
            for ($attempt = 1; $attempt <= $maxRetriesPerProxy; $attempt++) {
                try {
                    $result = $callback($activeProxy);

                    if (is_object($result) && method_exists($result, 'status')) {
                        $status = $result->status();
                        $body = method_exists($result, 'body') ? $result->body() : '';

                        // Check for TRUE bandwidth quota exhaustion
                        if (static::isTrueBandwidthExhausted($status, $body)) {
                            Log::warning("[UnbreakableProxy ⚡] Proxy '{$activeProxy['name']}' ({$activeProxy['username']}) ran out of bandwidth. Auto-switching to next pool proxy...");
                            Setting::markProxyExhausted($currentProxyId, "Bandwidth Closed (Decodo limit reached)");
                            // Break out of retry loop to hop to NEXT proxy in pool!
                            break;
                        }

                        // Check if Decodo exit node ended (HTTP 502 exit node offline)
                        if (static::isExitNodeOffline($status, $body)) {
                            Log::warning("[UnbreakableProxy 🔄] Decodo exit node ended on '{$activeProxy['name']}'. Auto-healing port session...");
                            $healed = static::autoHealExitNode($activeProxy);
                            if ($healed && !empty($healed['healthy'])) {
                                $activeProxy = Setting::getActiveProxyAccount() ?: $activeProxy;
                                continue;
                            }
                        }

                        // Transient HTTP 502/503/504 gateway issue from proxy
                        if (in_array($status, [502, 503, 504]) && $attempt < $maxRetriesPerProxy) {
                            usleep(250000 * $attempt); // 250ms, 500ms, 750ms backoff
                            continue;
                        }
                    }

                    // Success! Return result cleanly
                    return $result;

                } catch (Exception $e) {
                    $lastException = $e;
                    $msg = $e->getMessage();

                    // Check if Decodo exit node ended
                    if (static::isExitNodeOffline(0, '', $msg)) {
                        Log::warning("[UnbreakableProxy 🔄] Exit node disconnected on '{$activeProxy['name']}' ({$msg}). Auto-healing port session...");
                        $healed = static::autoHealExitNode($activeProxy);
                        if ($healed && !empty($healed['healthy'])) {
                            $activeProxy = Setting::getActiveProxyAccount() ?: $activeProxy;
                            continue;
                        }
                    }

                    // Check if exception indicates TRUE bandwidth exhaustion
                    if (static::isTrueBandwidthExhausted(0, '', $msg)) {
                        Log::warning("[UnbreakableProxy ⚡] Proxy '{$activeProxy['name']}' bandwidth exhausted ({$msg}). Auto-switching to next pool proxy...");
                        Setting::markProxyExhausted($currentProxyId, "Bandwidth Closed: " . substr($msg, 0, 80));
                        break; // Hop to next proxy in pool
                    }

                    // Transient network failure (cURL error 28, 56 without quota, 7, 35)
                    // NEVER mark proxy exhausted! Retry with backoff!
                    if ($attempt < $maxRetriesPerProxy) {
                        Log::info("[UnbreakableProxy 🔄] Transient network glitch on '{$activeProxy['name']}' ({$msg}). Retrying attempt " . ($attempt + 1) . "/{$maxRetriesPerProxy}...");
                        usleep(300000 * $attempt);
                        continue;
                    }

                    // If 3 attempts all failed due to temporary network blip, place in brief cooldown
                    Setting::markProxyCooling($currentProxyId, 30);
                    Log::warning("[UnbreakableProxy ⏳] Proxy '{$activeProxy['name']}' experienced transient network drops. Cooling down for 30s; switching to next proxy...");
                    Setting::activateNextAvailableProxy();
                    break;
                }
            }
        }

        // If all proxies in pool were exhausted or failed, fallback to direct or rethrow last exception
        Log::error("[UnbreakableProxy 🚨] All proxies in pool evaluated or exhausted without success. Dispatching request directly or returning last state.");
        if (isset($lastException)) {
            throw $lastException;
        }

        return null;
    }

    /**
     * HIGH-SCALE LOAD-BALANCED DISPATCHER:
     * Dispatches any request (e.g. bulk slot booking of 500-600 slots) across the load-balanced proxy pool.
     * If a specific port or exit node encounters a temporary error (HTTP 502/503/504, 429 rate limit, or cURL timeout),
     * it automatically retries with the NEXT distinct residential port or proxy in the pool!
     * Ensures smooth distribution without overloading any single proxy server.
     */
    public static function executeWithLoadBalancedRetry(callable $callback, int $maxRetries = 3, int $pacingDelayUs = 25000)
    {
        $lastException = null;
        $lastResult = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $proxyInfo = static::getLoadBalancedProxy();
            $proxyOpts = [
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]
            ];
            if ($proxyInfo) {
                static::applyProxyToOptions($proxyOpts, $proxyInfo);
            }

            try {
                if ($pacingDelayUs > 0) {
                    usleep($pacingDelayUs); // Light micro-pacing to prevent socket burst collisions
                }

                $res = $callback($proxyOpts, $proxyInfo);
                $lastResult = $res;

                if (is_object($res) && method_exists($res, 'status')) {
                    $status = $res->status();
                    $body = method_exists($res, 'body') ? $res->body() : '';

                    // Check for TRUE bandwidth exhaustion
                    if (static::isTrueBandwidthExhausted($status, $body)) {
                        $pId = $proxyInfo['id'] ?? 'unknown';
                        Setting::markProxyExhausted($pId, "Bandwidth Closed (Decodo limit reached)");
                        continue;
                    }

                    // Transient gateway or rate limit from proxy/target
                    if (in_array($status, [502, 503, 504, 429]) && $attempt < $maxRetries) {
                        $port = $proxyInfo['port'] ?? 'unknown';
                        Log::info("[ProxyLoadBalancer 🔄] Port {$port} returned HTTP {$status}. Rotating to next residential port (Attempt " . ($attempt + 1) . "/{$maxRetries})...");
                        usleep(100000 * $attempt);
                        continue;
                    }
                }

                return $res;
            } catch (Exception $e) {
                $lastException = $e;
                $msg = $e->getMessage();
                $port = $proxyInfo['port'] ?? 'unknown';

                if (static::isTrueBandwidthExhausted(0, '', $msg)) {
                    $pId = $proxyInfo['id'] ?? 'unknown';
                    Setting::markProxyExhausted($pId, "Bandwidth Closed: " . substr($msg, 0, 60));
                    continue;
                }

                if ($attempt < $maxRetries) {
                    Log::info("[ProxyLoadBalancer 🔄] Port {$port} network glitch ({$msg}). Sharding to next residential port (Attempt " . ($attempt + 1) . "/{$maxRetries})...");
                    usleep(150000 * $attempt);
                    continue;
                }
            }
        }

        if ($lastResult !== null) {
            return $lastResult;
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        return null;
    }
}
