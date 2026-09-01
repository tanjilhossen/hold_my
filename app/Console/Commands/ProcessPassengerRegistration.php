<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Passenger;
use App\Services\TaqamulService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessPassengerRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taqamul:process-passenger {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a candidate registration in headless background';

    /**
     * Execute the console command.
     */
    public function handle(TaqamulService $taqamulService)
    {
        $id = $this->argument('id');
        $passenger = Passenger::find($id);
        if (!$passenger) {
            $this->error("Passenger with ID {$id} not found.");
            Log::error("ProcessPassengerRegistration: Passenger {$id} not found.");
            return 1;
        }

        $passenger->update(['status' => 'processing']);

        $botData = [
            'passenger_id' => $passenger->id,
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'no_last_name' => $passenger->no_last_name,
            'passport_number' => $passenger->passport_number,
            'national_id' => $passenger->national_id,
            'gender' => $passenger->gender,
            'date_of_birth' => $passenger->date_of_birth,
            'passport_expiration_date' => $passenger->passport_expiration_date,
            'country_name' => $passenger->country_name ?: 'Bangladesh',
            'nationality_name' => $passenger->nationality_name ?: 'Bangladesh',
            'passport_file_path' => $passenger->passport_file_path && Storage::disk('public')->exists($passenger->passport_file_path) ? Storage::disk('public')->path($passenger->passport_file_path) : null,
            'personal_photo_path' => $passenger->personal_photo_path && Storage::disk('public')->exists($passenger->personal_photo_path) ? Storage::disk('public')->path($passenger->personal_photo_path) : null,
            'education_level' => $passenger->education_level,
            'experience_level' => $passenger->experience_level,
            'institute_name' => $passenger->institute_name,
            'email' => $passenger->email,
            'temp_mail_token' => $passenger->temp_mail_token,
            'temp_mail_password' => $passenger->temp_mail_password,
            'phone_number' => $passenger->phone_number,
            'country_code' => $passenger->country_code,
            'password' => $passenger->password,
            'preferable_contact' => $passenger->preferable_contact,
        ];

        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $browserMode = 'headed';
            $isHeadless = false;
        } else {
            $browserMode = \App\Models\Setting::get('browser_mode', 'headless');
            $isHeadless = ($browserMode !== 'headed');
        }

        $botData['passenger_id'] = $passenger->id;
        $botData['browser_mode'] = $browserMode;
        $botData['headless'] = $isHeadless;

        putenv("HEADLESS=" . ($isHeadless ? 'true' : 'false'));
        $_ENV['HEADLESS'] = ($isHeadless ? 'true' : 'false');

        Log::info("ProcessPassengerRegistration: Starting background bot for Passenger {$passenger->id} ({$passenger->passport_number}) [Mode: {$browserMode} / Headless: " . ($isHeadless ? 'YES' : 'NO') . "]");
        $botRes = $taqamulService->runBrowserAutomation($botData);

        if ($botRes['success'] || (isset($botRes['status']) && $botRes['status'] === 'completed')) {
            $passenger->update([
                'status' => 'AC Done',
                'email' => $botRes['email'] ?? $passenger->email,
                'password' => $botRes['password'] ?? $passenger->password,
                'otp_code' => $botRes['otp_code'] ?? null,
                'taqamul_response' => json_encode($botRes),
            ]);
            Log::info("ProcessPassengerRegistration: Passenger {$passenger->id} registration completed successfully.");
            $this->info("Registration completed for {$passenger->passport_number}");

            // 📢 Send Telegram Notification
            try {
                app(\App\Services\TelegramService::class)->sendCandidateNotification($passenger->fresh(['user']), 'completed');
            } catch (\Exception $e) {
                Log::error("Telegram notification error: " . $e->getMessage());
            }

            return 0;
        } else {
            $errorMessage = $botRes['error'] ?? 'Automation failed in background';
            $passenger->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'taqamul_response' => json_encode($botRes),
            ]);
            Log::error("ProcessPassengerRegistration: Passenger {$passenger->id} registration failed: {$errorMessage}");
            $this->error("Registration failed for {$passenger->passport_number}: {$errorMessage}");

            // 📢 Send Telegram Notification on Failure
            try {
                app(\App\Services\TelegramService::class)->sendCandidateNotification($passenger->fresh(['user']), 'failed', $errorMessage);
            } catch (\Exception $e) {
                Log::error("Telegram notification error: " . $e->getMessage());
            }

            return 1;
        }
    }
}
