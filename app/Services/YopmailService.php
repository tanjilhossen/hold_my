<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class YopmailService
{
    /**
     * Generate a unique YOPmail address for a candidate
     */
    public function generateEmail(string $passportNo): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($passportNo));
        if (empty($clean)) {
            $clean = 'candidate_' . rand(100000, 999999);
        }
        return "taqamul_{$clean}@yopmail.com";
    }

    /**
     * Poll YOPmail inbox and extract 6-digit OTP
     */
    public function checkOtp(string $email): array
    {
        $user = preg_replace('/@yopmail\.com$/i', '', $email);
        $scriptPath = base_path('bot/yopmail_reader.js');
        $cmd = "node \"{$scriptPath}\" \"{$user}\" 2>&1";

        $output = shell_exec($cmd);
        Log::info("Yopmail Reader output for {$email}: " . $output);

        if (preg_match('/FINAL_RESULT:(.*)$/m', $output, $matches)) {
            $data = json_decode(trim($matches[1]), true);
            if (is_array($data)) {
                return $data;
            }
        }

        // Regex fallback from raw output
        if (preg_match('/\b\d{6}\b/', $output, $otpMatch)) {
            return [
                'success' => true,
                'otp_found' => true,
                'otp' => $otpMatch[0]
            ];
        }

        return [
            'success' => true,
            'otp_found' => false,
            'message' => 'No OTP found yet in YOPmail inbox.'
        ];
    }
}
