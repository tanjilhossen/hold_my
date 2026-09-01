<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TaqamulService
{
    protected string $baseUrl = 'https://svp-international.pacc.sa/api';

    /**
     * Standard headers mimicking a real browser session
     */
    protected function getHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Origin' => 'https://svp-international.pacc.sa',
            'Referer' => 'https://svp-international.pacc.sa/auth/register?role=labor',
            'sec-ch-ua' => '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
        ];
    }

    /**
     * Step 1/2/3/4: Validate Registration Data (Direct HTTP)
     */
    public function validateStep(array $fields, array $files = []): array
    {
        try {
            $client = Http::withHeaders($this->getHeaders())->timeout(30);

            // Attach multipart files if present
            foreach ($files as $name => $filePath) {
                if ($filePath && file_exists($filePath)) {
                    $client = $client->attach($name, file_get_contents($filePath), basename($filePath));
                }
            }

            $url = "{$this->baseUrl}/individual_labor_space/registrations/validate";
            $response = $client->post($url, $fields);

            $status = $response->status();
            $rawBody = $response->body();
            $body = $response->json();

            // Detect Web Application Firewall (WAF) block
            if (str_contains($rawBody, 'Your request was blocked') || str_contains($rawBody, 'Support ID')) {
                return [
                    'success' => false,
                    'status_code' => 403,
                    'data' => null,
                    'error' => 'Taqamul WAF/Firewall blocked the request. Stealth Browser Engine is required.',
                    'raw' => $rawBody
                ];
            }

            $isSuccess = $response->successful();
            $errorMsg = null;
            if (!$isSuccess) {
                if (is_array($body) && isset($body['errors'])) {
                    $errorMsg = json_encode($body['errors']);
                } elseif (is_array($body) && isset($body['message'])) {
                    $errorMsg = $body['message'];
                } else {
                    $errorMsg = "HTTP Status {$status}: " . substr(strip_tags($rawBody), 0, 150);
                }
            }

            return [
                'success' => $isSuccess,
                'status_code' => $status,
                'data' => $body,
                'error' => $errorMsg,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resend OTP for registration
     */
    public function resendOtp(array $fields): array
    {
        try {
            $url = "{$this->baseUrl}/individual_labor_space/registrations/resend_otp";
            $response = Http::withHeaders($this->getHeaders())->timeout(20)->post($url, $fields);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Step 5: Final Labor Registration Submission
     */
    public function submitRegistration(array $fields, array $files = []): array
    {
        try {
            $client = Http::withHeaders($this->getHeaders())->timeout(45);

            // Attach files
            foreach ($files as $name => $filePath) {
                if ($filePath && file_exists($filePath)) {
                    $client = $client->attach($name, file_get_contents($filePath), basename($filePath));
                }
            }

            $url = "{$this->baseUrl}/individual_labor_space/registrations";
            $response = $client->post($url, $fields);

            $body = $response->json();
            $status = $response->status();

            return [
                'success' => $response->successful(),
                'status_code' => $status,
                'data' => $body,
                'error' => $response->successful() ? null : ($body['errors'] ?? $body['message'] ?? 'Registration submission failed'),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run Stealth Browser Automation Engine
     */
    public function runBrowserAutomation(array $candidateData): array
    {
        $botDir = base_path('bot');
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $browserMode = 'headed';
            $isHeadless = false;
        } else {
            $browserMode = $candidateData['browser_mode'] ?? \App\Models\Setting::get('browser_mode', 'headless');
            $isHeadless = ($browserMode !== 'headed');
        }

        $candidateData['browser_mode'] = $browserMode;
        $candidateData['headless'] = $isHeadless;

        $tempJson = $botDir . '/temp_' . uniqid() . '.json';
        file_put_contents($tempJson, json_encode($candidateData, JSON_PRETTY_PRINT));

        $nodeScript = $botDir . '/taqamul_bot.js';
        $headlessStr = $isHeadless ? 'true' : 'false';
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $cmd = "set \"HEADLESS={$headlessStr}\" && node \"{$nodeScript}\" \"{$tempJson}\" 2>&1";
        } else {
            $cmd = "HEADLESS={$headlessStr} node \"{$nodeScript}\" \"{$tempJson}\" 2>&1";
        }

        Log::info("Starting Browser Bot (Mode: {$browserMode} / Headless: {$headlessStr}): {$cmd}");
        $output = shell_exec($cmd);

        if (file_exists($tempJson)) {
            @unlink($tempJson);
        }

        Log::info("Bot Execution Output: " . $output);

        // Check if output contains FINAL_RESULT
        if (preg_match('/FINAL_RESULT:(.*)$/m', $output, $matches)) {
            $jsonStr = trim($matches[1]);
            $result = json_decode($jsonStr, true);
            if (is_array($result)) {
                return $result;
            }
        }

        if (str_contains($output, 'OTP request submitted successfully')) {
            return [
                'success' => true,
                'email' => $candidateData['email'] ?? '',
                'output' => $output
            ];
        }

        return [
            'success' => false,
            'error' => 'Stealth browser automation finished. ' . substr($output, 0, 300),
            'output' => $output
        ];
    }
}
