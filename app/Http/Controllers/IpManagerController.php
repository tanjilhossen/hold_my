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
            'proxyAccounts'
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

        $newAccount = [
            'id' => $newId,
            'name' => trim($request->input('name')),
            'host' => trim($request->input('host')),
            'port' => trim($request->input('port')),
            'username' => trim($request->input('username')),
            'password' => trim($request->input('password')),
            'status' => $desiredStatus,
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
     * Set a Proxy Account as Active
     */
    public function activateAccount(string $id)
    {
        $account = Setting::activateProxyAccount($id);
        if ($account) {
            return redirect()->back()->with('success', "Proxy account '{$account['name']}' activated successfully!");
        }

        return redirect()->back()->with('error', 'Failed to activate proxy account.');
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

        if (!str_starts_with($urlInput, 'http://') && !str_starts_with($urlInput, 'https://')) {
            $targetUrl = "http://{$urlInput}";
        } else {
            $targetUrl = $urlInput;
        }

        $startTime = microtime(true);

        try {
            $ch = curl_init($targetUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_PROXY, "{$host}:{$port}");
            if (!empty($user) && !empty($pass)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$user}:{$pass}");
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $result = curl_exec($ch);
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($result && $httpCode >= 200 && $httpCode < 400) {
                return response()->json([
                    'success' => true,
                    'message' => 'Proxy connection successful!',
                    'http_code' => $httpCode,
                    'latency_ms' => $latency,
                    'proxy_endpoint' => "{$host}:{$port}",
                    'raw_response' => $result,
                ]);
            }

            // Check if response indicates proxy data exhaustion / auth error
            if ($httpCode === 407 || str_contains($error, '56') || str_contains(strtolower($result), 'quota exceeded')) {
                $accountId = $request->input('account_id');
                Setting::markProxyExhausted($accountId ?: $user, "HTTP {$httpCode} - Test failed: " . ($error ?: 'Proxy Auth/MB Exhausted'));
            }

            return response()->json([
                'success' => false,
                'message' => 'Proxy connection failed. Error: ' . ($error ?: "HTTP Code {$httpCode}"),
                'http_code' => $httpCode,
                'latency_ms' => $latency,
                'raw_response' => $result ?: null,
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception testing proxy: ' . $e->getMessage(),
            ], 500);
        }
    }
}
