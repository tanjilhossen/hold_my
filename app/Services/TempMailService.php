<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;

class TempMailService
{
    protected string $baseUrl = 'https://api.mail.tm';

    /**
     * Get available domains from mail.tm
     */
    public function getDomains(): array
    {
        try {
            $res = Http::timeout(10)->get("{$this->baseUrl}/domains");
            if ($res->successful()) {
                return $res->json()['hydra:member'] ?? [];
            }
        } catch (Exception $e) {
            // fallback
        }
        return [];
    }

    /**
     * Create a new temporary mailbox
     */
    public function createMailbox(?string $prefix = null, ?string $password = null): array
    {
        $domains = $this->getDomains();
        if (empty($domains)) {
            // default domain if API returns empty
            $domain = 'txcct.com';
        } else {
            $domain = $domains[0]['domain'];
        }

        $prefix = $prefix ? preg_replace('/[^a-zA-Z0-9]/', '', strtolower($prefix)) : 'svpi_' . Str::random(8);
        $email = $prefix . '@' . $domain;
        $password = $password ?: 'Taqamul@' . rand(1000, 9999) . '!';

        $res = Http::timeout(15)->post("{$this->baseUrl}/accounts", [
            'address' => $email,
            'password' => $password,
        ]);

        if (!$res->successful()) {
            // Retry with randomized username
            $email = 'worker_' . Str::random(10) . '@' . $domain;
            $res = Http::timeout(15)->post("{$this->baseUrl}/accounts", [
                'address' => $email,
                'password' => $password,
            ]);
        }

        $accountData = $res->json();

        // Obtain bearer token
        $tokenRes = Http::timeout(15)->post("{$this->baseUrl}/token", [
            'address' => $email,
            'password' => $password,
        ]);

        $token = $tokenRes->json()['token'] ?? null;

        return [
            'id' => $accountData['id'] ?? null,
            'email' => $email,
            'password' => $password,
            'token' => $token,
            'domain' => $domain,
        ];
    }

    /**
     * Fetch messages from mailbox using bearer token
     */
    public function getMessages(string $token): array
    {
        try {
            $res = Http::withToken($token)->timeout(10)->get("{$this->baseUrl}/messages");
            if ($res->successful()) {
                return $res->json()['hydra:member'] ?? [];
            }
        } catch (Exception $e) {
            // ignore
        }
        return [];
    }

    /**
     * Read a specific message and extract OTP
     */
    public function getMessageContent(string $token, string $messageId): ?string
    {
        try {
            $res = Http::withToken($token)->timeout(10)->get("{$this->baseUrl}/messages/{$messageId}");
            if ($res->successful()) {
                $data = $res->json();
                return $data['text'] ?? $data['html'][0] ?? $data['intro'] ?? '';
            }
        } catch (Exception $e) {
            // ignore
        }
        return null;
    }

    /**
     * Poll mailbox and automatically extract 6-digit OTP
     */
    public function fetchLatestOtp(string $token): ?string
    {
        $messages = $this->getMessages($token);
        if (empty($messages)) {
            return null;
        }

        // Look through recent messages
        foreach ($messages as $msg) {
            $subject = $msg['subject'] ?? '';
            $intro = $msg['intro'] ?? '';
            
            // Check in subject and intro
            if (preg_match('/\b([0-9]{6})\b/', $subject . ' ' . $intro, $matches)) {
                return $matches[1];
            }

            // Fetch full content
            $content = $this->getMessageContent($token, $msg['id']);
            if ($content && preg_match('/\b([0-9]{6})\b/', $content, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
