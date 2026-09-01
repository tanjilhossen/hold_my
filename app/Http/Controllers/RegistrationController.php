<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use App\Models\Setting;
use App\Services\TaqamulService;
use App\Services\WafidMailService;
use App\Services\TempMailService;
use App\Services\YopmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class RegistrationController extends Controller
{
    protected TaqamulService $taqamulService;
    protected WafidMailService $wafidMailService;
    protected TempMailService $tempMailService;
    protected YopmailService $yopmailService;

    public function __construct(
        TaqamulService $taqamulService, 
        WafidMailService $wafidMailService, 
        TempMailService $tempMailService, 
        YopmailService $yopmailService
    ) {
        $this->taqamulService = $taqamulService;
        $this->wafidMailService = $wafidMailService;
        $this->tempMailService = $tempMailService;
        $this->yopmailService = $yopmailService;
    }

    /**
     * Display the full Taqamul registration form
     */
    public function showForm()
    {
        return view('registration.form');
    }

    /**
     * Process automated registration with required file uploads and full 4-step bot
     */
    public function processRegistration(Request $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', '300');
        
        $request->validate([
            'first_name' => 'required|string|max:100',
            'passport_number' => 'required|string|max:50',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date',
            'passport_expiration_date' => 'required|date',
            'education_level' => 'required|string',
            'experience_level' => 'required|string',
            'passport_file' => 'required|file|mimes:jpeg,png,jpg|max:5120',
            'personal_photo' => 'required|file|mimes:jpeg,png,jpg|max:5120',
        ]);

        try {
            $firstName = trim($request->input('first_name'));
            $lastName = trim($request->input('last_name', ''));
            $noLastName = $request->boolean('no_last_name') || empty($lastName);
            $passportNo = strtoupper(trim($request->input('passport_number')));
            $nationalId = trim($request->input('national_id', ''));
            $gender = $request->input('gender', 'male');
            $dob = $request->input('date_of_birth');
            $passportExp = $request->input('passport_expiration_date');
            
            $countryId = $request->input('country_id', '1');
            $countryName = $request->input('country_name', 'Bangladesh');
            $nationalityId = $request->input('nationality_id', '1');
            $nationalityName = $request->input('nationality_name', 'Bangladeshi');
            
            $education = $request->input('education_level', 'no_educational_qualification');
            $experience = $request->input('experience_level', 'no_experience');
            $institute = $request->input('institute_name', 'No, I don’t have any certifications or training');
            $customInstitute = $request->input('custom_institute_name');
            
            $phone = $request->input('phone_number', '');
            $countryCode = $request->input('country_code', '+880');
            $preferableContact = $request->input('preferable_contact', Setting::get('default_otp_delivery_method', 'email'));
            
            $password = $request->input('password');
            if (empty($password)) {
                $password = Setting::get('default_password', 'Taqamul@2026!');
            }

            // Required File uploads storage
            $passportFilePath = $request->file('passport_file')->store('passengers/passports', 'public');
            $personalPhotoPath = $request->file('personal_photo')->store('passengers/photos', 'public');

            // Provision Email Address (According to Admin Settings default)
            $cleanFirst = preg_replace('/[^a-z0-9]/', '', strtolower($firstName));
            $cleanLast = preg_replace('/[^a-z0-9]/', '', strtolower($lastName ?: 'hasan'));
            $cleanPassport = preg_replace('/[^a-z0-9]/', '', strtolower($passportNo));
            $mailboxName = "{$cleanFirst}{$cleanLast}_{$cleanPassport}";

            $emailMode = $request->input('email_mode', Setting::get('default_email_provision_method', 'auto_wafidmail'));

            if ($emailMode === 'custom_mail' && $request->filled('email')) {
                $email = trim($request->input('email'));
            } elseif ($emailMode === 'auto_yopmail') {
                $email = "{$mailboxName}@yopmail.com";
            } else {
                // Default: @renonx.tech private anonymous mail server
                $email = "{$mailboxName}@renonx.tech";
                try {
                    $wafidRes = $this->wafidMailService->createMailbox($mailboxName);
                    if (!empty($wafidRes['email'])) {
                        $email = $wafidRes['email'];
                    }
                } catch (Exception $e) {
                    Log::info("WafidMail fallback to default name: {$email}");
                }
            }

            // Create Passenger Record with status = 'processing'
            $passenger = Passenger::create([
                'user_id' => Auth::id(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'no_last_name' => $noLastName,
                'passport_number' => $passportNo,
                'national_id' => $nationalId ?: $passportNo,
                'gender' => $gender,
                'date_of_birth' => $dob,
                'passport_expiration_date' => $passportExp,
                'country_id' => $countryId,
                'country_name' => $countryName,
                'nationality_id' => $nationalityId,
                'nationality_name' => $nationalityName,
                'passport_file_path' => $passportFilePath,
                'personal_photo_path' => $personalPhotoPath,
                'education_level' => $education,
                'experience_level' => $experience,
                'institute_name' => $institute,
                'custom_institute_name' => $customInstitute,
                'email' => $email,
                'password' => $password,
                'temp_mail_token' => null,
                'temp_mail_password' => null,
                'country_code' => $countryCode,
                'phone_number' => $phone,
                'preferable_contact' => $preferableContact,
                'status' => 'processing',
            ]);

            // Launch Headless Automation in the background asynchronously (Cross-platform Linux & Windows)
            if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
                $phpBin = file_exists("D:\\xampp\\php\\php.exe") ? "D:\\xampp\\php\\php.exe" : "php";
                $artisan = base_path('artisan');
                $cmd = "start /B \"\" \"{$phpBin}\" \"{$artisan}\" taqamul:process-passenger {$passenger->id} > NUL 2>&1";
                @pclose(popen($cmd, "r"));
            } else {
                $artisan = base_path('artisan');
                $cmd = "php \"{$artisan}\" taqamul:process-passenger {$passenger->id} > /dev/null 2>&1 &";
                @exec($cmd);
            }

            return response()->json([
                'success' => true,
                'status' => 'processing',
                'message' => 'Candidate registration started in background!',
                'redirect_url' => Auth::user() && Auth::user()->role === 'admin' ? route('admin.passengers.index') : route('user.dashboard'),
                'passenger' => [
                    'id' => $passenger->id,
                    'full_name' => $passenger->full_name,
                    'passport_number' => $passenger->passport_number,
                    'email' => $passenger->email,
                    'status' => 'processing',
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error("Registration Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Check Temp-Mail or YOPmail for OTP
     */
    public function checkOtp(Request $request)
    {
        $request->validate([
            'passenger_id' => 'required|exists:passengers,id',
        ]);

        $passenger = Passenger::findOrFail($request->passenger_id);

        if (str_contains(strtolower($passenger->email), '@yopmail.com')) {
            $res = $this->yopmailService->checkOtp($passenger->email);
            if (!empty($res['otp_found']) && !empty($res['otp'])) {
                $passenger->update(['otp_code' => $res['otp']]);
                return response()->json([
                    'success' => true,
                    'otp_found' => true,
                    'otp' => $res['otp'],
                    'message' => 'OTP extracted from YOPmail!',
                ]);
            }
            return response()->json([
                'success' => true,
                'otp_found' => false,
                'message' => 'Waiting for YOPmail incoming message...',
            ]);
        }

        if (!$passenger->temp_mail_token) {
            return response()->json([
                'success' => false,
                'otp_found' => false,
                'message' => 'No mailbox token found.',
            ]);
        }

        $otp = $this->tempMailService->fetchLatestOtp($passenger->temp_mail_token);
        if ($otp) {
            $passenger->update(['otp_code' => $otp]);
            return response()->json([
                'success' => true,
                'otp_found' => true,
                'otp' => $otp,
                'message' => 'OTP extracted from Temp-Mail!',
            ]);
        }

        return response()->json([
            'success' => true,
            'otp_found' => false,
            'message' => 'Waiting for incoming email...',
        ]);
    }

    /**
     * Final Registration Submission with OTP
     */
    public function submitFinalRegistration(Request $request)
    {
        $request->validate([
            'passenger_id' => 'required|exists:passengers,id',
            'otp_code' => 'required|string',
        ]);

        $passenger = Passenger::findOrFail($request->passenger_id);
        $otp = trim($request->input('otp_code'));

        $fields = [
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name ?: '',
            'first_name_not_specified' => false,
            'last_name_not_specified' => $passenger->no_last_name,
            'passport_number' => $passenger->passport_number,
            'national_id' => $passenger->national_id ?: $passenger->passport_number,
            'sex' => $passenger->gender,
            'date_of_birth' => $passenger->date_of_birth,
            'passport_expiration_date' => $passenger->passport_expiration_date,
            'country_id' => $passenger->country_id,
            'nationality_id' => $passenger->nationality_id,
            'education_level' => $passenger->education_level,
            'experience_level' => $passenger->experience_level,
            'institute_name' => $passenger->institute_name,
            'email' => $passenger->email,
            'phone_number' => $passenger->phone_number,
            'country_code' => $passenger->country_code,
            'preferable_contact' => $passenger->preferable_contact,
            'password' => $passenger->password,
            'password_confirmation' => $passenger->password,
            'data_accuracy_acknowledged' => '1',
            'terms_and_privacy_accepted' => '1',
            'document_type' => 'passport',
            'confirmation_type' => $passenger->preferable_contact,
            'otp_code' => $otp,
        ];

        $uploadFiles = [];
        if ($passenger->passport_file_path && Storage::disk('public')->exists($passenger->passport_file_path)) {
            $uploadFiles['passport'] = Storage::disk('public')->path($passenger->passport_file_path);
        }
        if ($passenger->personal_photo_path && Storage::disk('public')->exists($passenger->personal_photo_path)) {
            $uploadFiles['image'] = Storage::disk('public')->path($passenger->personal_photo_path);
        }

        $res = $this->taqamulService->submitRegistration($fields, $uploadFiles);

        if ($res['success']) {
            $passenger->update([
                'status' => 'AC Done',
                'otp_code' => $otp,
                'taqamul_response' => json_encode($res['data'] ?? []),
            ]);

            // 📢 Send Telegram Notification
            try {
                app(\App\Services\TelegramService::class)->sendCandidateNotification($passenger->fresh(['user']), 'completed');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Telegram notification error: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Candidate registered successfully on Taqamul!',
                'passenger' => $passenger,
            ]);
        } else {
            // 📢 Send Telegram Notification on Failure
            try {
                app(\App\Services\TelegramService::class)->sendCandidateNotification($passenger->fresh(['user']), 'failed', $res['error'] ?? 'Final submission failed');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Telegram notification error: " . $e->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => $res['error'] ?? 'Final submission failed',
            ], 422);
        }
    }
}
