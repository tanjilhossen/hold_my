<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Exception;

class IpManagerController extends Controller
{
    /**
     * Render IP Manager Dashboard View
     */
    public function index()
    {
        $proxyEnabled = Setting::get('proxy_enabled', '1');
        $proxyTestUrl = Setting::get('proxy_test_url', 'ip.decodo.com/json');
        $proxyAccounts = Setting::getProxyAccounts();
        $decodoApiKey = Setting::get('decodo_api_key', '');

        $activeAccount = Setting::getActiveProxyAccount();
        $proxyHost = $activeAccount['host'] ?? Setting::get('proxy_host', 'bd.decodo.com');
        $proxyPort = $activeAccount['port'] ?? Setting::get('proxy_port', '41001');
        $proxyUsername = $activeAccount['username'] ?? Setting::get('proxy_username', 'spua00a572');
        $proxyPassword = $activeAccount['password'] ?? Setting::get('proxy_password', 'o3PbblJqa5C6~vzo9M');

        return view('ip_manager.index', compact(
            'proxyEnabled',
            'proxyHost',
            'proxyPort',
            'proxyUsername',
            'proxyPassword',
            'proxyTestUrl',
            'proxyAccounts',
            'decodoApiKey'
        ));
    }

    /**
     * Update Global Proxy Settings
     */
    public function update(Request $request)
    {
        Setting::set('proxy_enabled', $request->has('proxy_enabled') ? '1' : '0');
        if ($request->has('proxy_test_url')) {
            Setting::set('proxy_test_url', trim($request->input('proxy_test_url', 'ip.decodo.com/json')));
        }
        if ($request->has('decodo_api_key')) {
            Setting::set('decodo_api_key', trim($request->input('decodo_api_key', '')));
        }

        return redirect()->back()->with('success', 'Proxy global settings updated successfully.');
    }

    /**
     * Store new Decodo Proxy Account
     */
    public function storeAccount(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'host' => 'required|string',
            'port' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
            'status' => 'nullable|string|in:active,idle',
        ]);

        $accounts = Setting::getProxyAccounts();
        $newId = 'decodo_' . uniqid();
        $desiredStatus = $request->input('status', 'idle');

        if ($desiredStatus === 'active') {
            foreach ($accounts as &$acc) {
                if (($acc['status'] ?? '') === 'active') {
                    $acc['status'] = 'idle';
                }
            }
            unset($acc);
        }
 
        $quotaLimit = $request->filled('quota_limit_mb') ? (float)$request->input('quota_limit_mb') : null;
        $remainingMb = $request->filled('remaining_mb') ? (float)$request->input('remaining_mb') : $quotaLimit;

        $newAccount = [
            'id' => $newId,
            'name' => trim($request->input('name')),
            'host' => trim($request->input('host')),
            'port' => trim($request->input('port')),
            'username' => trim($request->input('username')),
            'password' => trim($request->input('password')),
            'status' => $desiredStatus,
            'quota_limit_mb' => $quotaLimit,
            'remaining_mb' => $remainingMb,
            'bytes_used' => 0,
            'requests_count' => 0,
            'exhausted_at' => null,
            'exhausted_reason' => null,
            'notes' => trim($request->input('notes', '')),
        ];

        $accounts[] = $newAccount;
        Setting::saveProxyAccounts($accounts);

        if ($desiredStatus === 'active') {
            Setting::activateProxyAccount($newId);
        }

        return redirect()->back()->with('success', "Decodo proxy account '{$newAccount['name']}' added successfully!");
    }

    /**
     * Update existing Decodo Proxy Account
     */
    public function updateAccount(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'host' => 'required|string',
            'port' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
            'status' => 'required|string|in:active,idle,exhausted',
            'quota_limit_mb' => 'nullable|numeric|min:0',
            'remaining_mb' => 'nullable|numeric|min:0',
        ]);

        $accounts = Setting::getProxyAccounts();
        $updatedAccount = null;
        $newStatus = $request->input('status');

        foreach ($accounts as &$acc) {
            if ($acc['id'] === $id) {
                $acc['name'] = trim($request->input('name'));
                $acc['host'] = trim($request->input('host'));
                $acc['port'] = trim($request->input('port'));
                $acc['username'] = trim($request->input('username'));
                $acc['password'] = trim($request->input('password'));
                $acc['notes'] = trim($request->input('notes', ''));

                if ($request->has('quota_limit_mb') && $request->input('quota_limit_mb') !== null && $request->input('quota_limit_mb') !== '') {
                    $acc['quota_limit_mb'] = (float)$request->input('quota_limit_mb');
                }
                if ($request->has('remaining_mb') && $request->input('remaining_mb') !== null && $request->input('remaining_mb') !== '') {
                    $acc['remaining_mb'] = (float)$request->input('remaining_mb');
                } elseif (!empty($acc['quota_limit_mb'])) {
                    $usedMb = ($acc['bytes_used'] ?? 0) / (1024 * 1024);
                    $acc['remaining_mb'] = max(0, round($acc['quota_limit_mb'] - $usedMb, 2));
                }

                if ($newStatus !== 'exhausted') {
                    $acc['exhausted_at'] = null;
                    $acc['exhausted_reason'] = null;
                }

                $acc['status'] = $newStatus;
                $updatedAccount = $acc;
                break;
            }
        }
        unset($acc);

        if (!$updatedAccount) {
            return redirect()->back()->with('error', 'Proxy account not found.');
        }

        Setting::saveProxyAccounts($accounts);

        if ($newStatus === 'active') {
            Setting::activateProxyAccount($id);
        }

        return redirect()->back()->with('success', "Decodo proxy account '{$updatedAccount['name']}' updated successfully!");
    }

    /**
     * Quick-set allocated or remaining bandwidth (MB) for a proxy account
     */
    public function setAccountBandwidth(Request $request, string $id)
    {
        $request->validate([
            'quota_limit_mb' => 'required|numeric|min:0',
        ]);

        $quotaMb = (float)$request->input('quota_limit_mb');
        $accounts = Setting::getProxyAccounts();
        $target = null;

        foreach ($accounts as &$acc) {
            if ($acc['id'] === $id) {
                $acc['quota_limit_mb'] = $quotaMb;
                // Compute remaining MB based on usage or reset
                $usedMb = ($acc['bytes_used'] ?? 0) / (1024 * 1024);
                $remaining = max(0, round($quotaMb - $usedMb, 2));
                $acc['remaining_mb'] = $remaining;

                if ($remaining > 0 && $acc['status'] === 'exhausted') {
                    $acc['status'] = 'idle';
                    $acc['exhausted_at'] = null;
                    $acc['exhausted_reason'] = null;
                }

                $target = $acc;
                break;
            }
        }
        unset($acc);

        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        Setting::saveProxyAccounts($accounts);

        $formattedRemaining = $target['remaining_mb'] >= 1024 
            ? round($target['remaining_mb'] / 1024, 2) . ' GB' 
            : round($target['remaining_mb'], 1) . ' MB';

        return response()->json([
            'success' => true,
            'id' => $target['id'],
            'quota_limit_mb' => $target['quota_limit_mb'],
            'remaining_mb' => $target['remaining_mb'],
            'formatted_remaining' => $formattedRemaining,
            'status' => $target['status'],
            'message' => "Bandwidth quota updated to {$quotaMb} MB ({$formattedRemaining} remaining)!",
        ]);
    }

    /**
     * Delete Decodo Proxy Account
     */
    public function deleteAccount(string $id)
    {
        $accounts = Setting::getProxyAccounts();
        $filtered = array_values(array_filter($accounts, fn($acc) => $acc['id'] !== $id));

        if (count($filtered) === count($accounts)) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        Setting::saveProxyAccounts($filtered);

        // If active account was deleted, auto activate first available idle account
        $activeExists = false;
        foreach ($filtered as $acc) {
            if (($acc['status'] ?? '') === 'active') {
                $activeExists = true;
                break;
            }
        }

        if (!$activeExists && !empty($filtered)) {
            Setting::getActiveProxyAccount();
        }

        return redirect()->back()->with('success', 'Proxy account deleted successfully.');
    }

    /**
     * Set a Proxy Account as Active (and automatically refresh its live bandwidth)
     */
    public function activateAccount(string $id)
    {
        $account = Setting::activateProxyAccount($id);
        if ($account) {
            // Automatically refresh live bandwidth for the newly activated proxy
            try {
                \App\Services\ProxyService::checkSingleAccountBandwidth($id);
            } catch (Exception $e) {
                // Non-blocking
            }

            return redirect()->back()->with('success', "Proxy account '{$account['name']}' activated and live status refreshed successfully!");
        }

        return redirect()->back()->with('error', 'Failed to activate proxy account.');
    }

    /**
     * Check real live bandwidth for a single proxy account on-demand
     */
    public function checkAccountBandwidth(Request $request, string $id)
    {
        try {
            $result = \App\Services\ProxyService::checkSingleAccountBandwidth($id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking bandwidth: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Single Proxy Connection via cURL to target endpoint (e.g. ip.decodo.com/json)
     */
    public function testSingleAccount(Request $request)
    {
        $host = trim($request->input('proxy_host') ?: Setting::get('proxy_host', 'bd.decodo.com'));
        $port = trim($request->input('proxy_port') ?: Setting::get('proxy_port', '41001'));
        $user = trim($request->input('proxy_username') ?: Setting::get('proxy_username', 'spua00a572'));
        $pass = trim($request->input('proxy_password') ?: Setting::get('proxy_password', 'o3PbblJqa5C6~vzo9M'));
        $urlInput = trim($request->input('proxy_test_url') ?: Setting::get('proxy_test_url', 'ip.decodo.com/json'));

        $accountId = $request->input('account_id');
        $proxyInfo = [
            'id' => $accountId,
            'host' => $host,
            'port' => $port,
            'username' => $user,
            'password' => $pass,
        ];

        try {
            $health = \App\Services\ProxyService::checkHealth($proxyInfo, $urlInput, 12);

            if ($health['healthy']) {
                $endpoint = $health['proxy_endpoint'] ?? "{$host}:{$port}";
                $msg = !empty($health['auto_healed'])
                    ? "Residential exit node refreshed! Auto-connected to live session: {$endpoint}"
                    : 'Proxy connection & authentication successful!';

                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'http_code' => $health['http_status'],
                    'latency_ms' => $health['latency_ms'],
                    'external_ip' => $health['external_ip'],
                    'proxy_endpoint' => $endpoint,
                    'auto_healed' => !empty($health['auto_healed']),
                    'raw_response' => $health['raw_response'],
                ]);
            }

            // Check if response indicates TRUE bandwidth exhaustion
            if (!empty($health['is_bandwidth_exhausted'])) {
                $accountId = $request->input('account_id');
                Setting::markProxyExhausted($accountId ?: $user, "Bandwidth Closed: Traffic limit reached on Decodo server");
            }

            return response()->json([
                'success' => false,
                'message' => 'Proxy test failed: ' . ($health['error'] ?: "HTTP Code {$health['http_status']}"),
                'http_code' => $health['http_status'],
                'latency_ms' => $health['latency_ms'],
                'is_bandwidth_exhausted' => !empty($health['is_bandwidth_exhausted']),
                'raw_response' => $health['raw_response'] ?: null,
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception testing proxy: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync Real Server-Side Bandwidth from Decodo Public API
     */
    public function syncBandwidth(Request $request)
    {
        $apiKey = trim($request->input('decodo_api_key') ?: Setting::get('decodo_api_key', ''));
        if (!empty($apiKey)) {
            Setting::set('decodo_api_key', $apiKey);
        }

        $res = \App\Services\ProxyService::fetchDecodoApiBandwidth($apiKey);
        if ($res['success']) {
            return redirect()->back()->with('success', $res['message']);
        }

        return redirect()->back()->with('error', $res['message']);
    }

    /**
     * Reactivate all proxy accounts in pool (Reset Exhausted to Active/Idle)
     */
    public function reactivateAll()
    {
        $count = Setting::reactivateAllProxies();
        return redirect()->back()->with('success', "All {$count} proxy account(s) have been reactivated and verified for routing!");
    }
}
