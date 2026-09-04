<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class WafidMailService
{
    protected string $baseUrl;
    protected ?string $keyId;
    protected ?string $secretKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.wafid_mail.base_url', env('WAFID_MAIL_BASE_URL', 'https://mail.wafidmaster.com')), '/');
        $this->keyId = config('services.wafid_mail.key_id', env('WAFID_MAIL_KEY_ID'));
        $this->secretKey = config('services.wafid_mail.secret_key', env('WAFID_MAIL_SECRET_KEY'));
    }

    /**
     * Check if Wafid Mail service has API credentials configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->keyId) && !empty($this->secretKey);
    }

    /**
     * Generate HMAC-SHA256 signed headers for request
     */
    protected function getSignedHeaders(string $method, string $path, ?array $body = null): array
    {
        $timestamp = (string) time();
        $nonce = Str::uuid()->toString();

        $pathNoQuery = parse_url($path, PHP_URL_PATH);

        $bodyStr = ($body !== null && !empty($body)) ? json_encode($body, JSON_UNESCAPED_SLASHES) : '';
        $bodyHash = hash('sha256', $bodyStr);

        $canonicalStr = strtoupper($method) . "\n" . $pathNoQuery . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyHash;
        $signingKey = hash('sha256', (string) $this->secretKey);
        $signature = hash_hmac('sha256', $canonicalStr, $signingKey);

        $headers = [
            'Accept' => 'application/json',
            'X-API-Key' => $this->keyId,
            'X-API-Timestamp' => $timestamp,
            'X-API-Nonce' => $nonce,
            'X-API-Signature' => $signature,
        ];

        if (!empty($bodyStr)) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * Create or ensure a specific mailbox name
     */
    public function createMailbox(string $name): array
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($name));
        $path = '/api/v1/mailboxes';
        
        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'name' => $name,
                'email' => "{$name}@renonx.tech",
                'is_mock' => true,
            ];
        }

        try {
            $body = ['name' => $name];
            $bodyStr = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers = $this->getSignedHeaders('POST', $path, $body);
            $response = Http::withHeaders($headers)->timeout(15)->withBody($bodyStr, 'application/json')->post("{$this->baseUrl}{$path}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'id' => $data['id'] ?? null,
                    'name' => $data['name'] ?? $name,
                    'email' => $data['email'] ?? "{$name}@renonx.tech",
                    'data' => $data
                ];
            }

            Log::warning("WafidMail createMailbox error: HTTP {$response->status()} - {$response->body()}");
        } catch (Exception $e) {
            Log::error("WafidMail createMailbox exception: " . $e->getMessage());
        }

        return [
            'success' => false,
            'name' => $name,
            'email' => "{$name}@renonx.tech",
        ];
    }

    /**
     * Generate a random disposable mailbox
     */
    public function createRandomMailbox(): array
    {
        $path = '/api/v1/mailboxes/random';

        if (!$this->isConfigured()) {
            $rand = 'usr_' . Str::random(8);
            return [
                'success' => true,
                'name' => $rand,
                'email' => "{$rand}@renonx.tech",
            ];
        }

        try {
            $headers = $this->getSignedHeaders('POST', $path);
            $response = Http::withHeaders($headers)->timeout(15)->post("{$this->baseUrl}{$path}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'id' => $data['id'] ?? null,
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'data' => $data
                ];
            }
        } catch (Exception $e) {
            Log::error("WafidMail createRandomMailbox exception: " . $e->getMessage());
        }

        return [
            'success' => false,
            'email' => null
        ];
    }

    public function getBaseUrlForDomain(string $domain): string
    {
        $d = strtolower(trim($domain));
        if (str_contains($d, 'renonx.tech')) {
            return 'https://mail.renonx.tech';
        }
        if (str_contains($d, 'wafidmaster.com')) {
            return 'https://mail.wafidmaster.com';
        }
        return $this->baseUrl;
    }

    /**
     * Get messages list in a mailbox
     */
    /**
     * Get messages list in a mailbox
     */
    public function getMessages(string $mailboxName): array
    {
        $domain = str_contains($mailboxName, '@') ? strtolower(trim(explode('@', $mailboxName)[1])) : 'wafidmaster.com';
        $fullEmail = str_contains($mailboxName, '@') ? strtolower(trim($mailboxName)) : "{$mailboxName}@{$domain}";
        $baseUrl = $this->getBaseUrlForDomain($domain);

        try {
            // 1. Try Public AnonMail Endpoint (Fastest, direct match)
            $publicUrl = "{$baseUrl}/api/public/mailboxes/" . urlencode($fullEmail) . "/messages";
            $res = Http::timeout(6)->get($publicUrl);
            if ($res->successful() && !empty($res->json('messages'))) {
                return $res->json('messages');
            }

            // 2. Try clean name public endpoint
            $cleanName = preg_replace('/@.*$/', '', $fullEmail);
            $publicCleanUrl = "{$baseUrl}/api/public/mailboxes/" . urlencode($cleanName) . "/messages";
            $resClean = Http::timeout(6)->get($publicCleanUrl);
            if ($resClean->successful() && !empty($resClean->json('messages'))) {
                return $resClean->json('messages');
            }

            // 3. Fallback to HMAC signed endpoint
            $path = "/api/v1/mailboxes/{$cleanName}/messages?domain=" . urlencode($domain);
            $headers = $this->getSignedHeaders('GET', $path);
            $response = Http::withHeaders($headers)->timeout(8)->get("{$baseUrl}{$path}");
            if ($response->successful() && !empty($response->json('messages'))) {
                return $response->json('messages');
            }
        } catch (Exception $e) {
            Log::error("WafidMail getMessages exception: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Get full email content (HTML, text, etc)
     */
    public function getMessageDetail(string $messageId, string $domain = 'wafidmaster.com'): ?array
    {
        $baseUrl = $this->getBaseUrlForDomain($domain);

        try {
            // 1. Try Public AnonMail message detail endpoint
            $publicUrl = "{$baseUrl}/api/public/messages/" . urlencode($messageId);
            $res = Http::timeout(6)->get($publicUrl);
            if ($res->successful() && !empty($res->json())) {
                return $res->json();
            }

            // 2. Fallback to HMAC signed endpoint
            $path = "/api/v1/messages/{$messageId}";
            $headers = $this->getSignedHeaders('GET', $path);
            $response = Http::withHeaders($headers)->timeout(8)->get("{$baseUrl}{$path}");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (Exception $e) {
            Log::error("WafidMail getMessageDetail exception: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Poll until latest OTP arrives
     */
    public function waitForLatestOtp(string $mailboxName, int $timeoutSec = 45, ?callable $logger = null): ?string
    {
        $domain = str_contains($mailboxName, '@') ? strtolower(trim(explode('@', $mailboxName)[1])) : 'renonx.tech';
        $cleanName = preg_replace('/@.*$/', '', $mailboxName);
        $startTime = time();

        while ((time() - $startTime) < $timeoutSec) {
            $elapsed = time() - $startTime;
            if ($logger) {
                $logger("[WafidMail API ⏳] Polling inbox for {$cleanName}@{$domain} ({$elapsed}s/{$timeoutSec}s)...");
            }

            $messages = $this->getMessages($mailboxName);

            if (!empty($messages)) {
                $latestMsg = $messages[0];
                
                // Check subject or snippet for OTP
                $combined = ($latestMsg['subject'] ?? '') . ' ' . ($latestMsg['snippet'] ?? '');
                if (preg_match('/\b([0-9]{6})\b/', $combined, $match)) {
                    return $match[1];
                }

                // If not in snippet, fetch full message body
                $detail = $this->getMessageDetail($latestMsg['id'], $domain);
                if ($detail) {
                    $bodyText = ($detail['text_body'] ?? '') . ' ' . ($detail['html_body'] ?? '') . ' ' . ($detail['text_content'] ?? '');
                    if (preg_match('/\b([0-9]{6})\b/', $bodyText, $match)) {
                        return $match[1];
                    }
                }
            }

            sleep(2);
        }

        return null;
    }
}
