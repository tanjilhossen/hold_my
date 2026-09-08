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
        $proxyHost = Setting::get('proxy_host', 'bd.decodo.com');
        $proxyPort = Setting::get('proxy_port', '41001');
        $proxyUsername = Setting::get('proxy_username', 'spua00a572');
        $proxyPassword = Setting::get('proxy_password', 'o3PbblJqa5C6~vzo9M');
        $proxyTestUrl = Setting::get('proxy_test_url', 'ip.decodo.com/json');

        return view('ip_manager.index', compact(
            'proxyEnabled',
            'proxyHost',
            'proxyPort',
            'proxyUsername',
            'proxyPassword',
            'proxyTestUrl'
        ));
    }

    /**
     * Update Proxy Configurations
     */
    public function update(Request $request)
    {
        $request->validate([
            'proxy_host' => 'required|string',
            'proxy_port' => 'required|string',
            'proxy_username' => 'nullable|string',
            'proxy_password' => 'nullable|string',
            'proxy_test_url' => 'nullable|string',
        ]);

        Setting::set('proxy_enabled', $request->has('proxy_enabled') ? '1' : '0');
        Setting::set('proxy_host', trim($request->input('proxy_host')));
        Setting::set('proxy_port', trim($request->input('proxy_port')));
        Setting::set('proxy_username', trim($request->input('proxy_username')));
        Setting::set('proxy_password', trim($request->input('proxy_password')));
        Setting::set('proxy_test_url', trim($request->input('proxy_test_url', 'ip.decodo.com/json')));

        return redirect()->back()->with('success', 'Proxy configurations updated successfully.');
    }

    /**
     * Test Proxy Connection via cURL to target endpoint (e.g. ip.decodo.com/json)
     */
    public function testConnection(Request $request)
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
