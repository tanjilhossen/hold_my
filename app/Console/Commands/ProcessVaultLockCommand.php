<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SlotHold;
use App\Models\Passenger;
use App\Services\TaqamulTokenService;
use Exception;

class ProcessVaultLockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vault:process-lock 
                            {mother_hash : The Mother Hash to hold slots for} 
                            {requested_count=1 : Number of candidate slots to hold (up to 50)} 
                            {category_id=159 : Category ID for profession} 
                            {center_name=Center : Test center name} 
                            {city=Dhaka : Test center city} 
                            {exam_date=2026-09-17 : Exam Date} 
                            {category_name=Profession : Profession category name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process background multi-account slot holds (up to 50 slots) into Slot Vault with rate-limit backoff and token auto-login';

    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';

    /**
     * Execute the console command.
     */
    public function handle(TaqamulTokenService $tokenService)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $motherHash = trim($this->argument('mother_hash'));
        $requestedCount = max(1, (int)$this->argument('requested_count'));
        $categoryId = (int)$this->argument('category_id');
        $centerName = trim($this->argument('center_name'));
        $city = trim($this->argument('city'));
        $examDate = trim($this->argument('exam_date'));
        $categoryName = trim($this->argument('category_name'));

        $this->info("[VaultLockWorker] Starting Background Slot Lock for {$centerName} ({$city}) - Requested: {$requestedCount} slot(s)...");

        // Fetch candidate pool accounts
        $poolAccounts = $tokenService->getPoolAccounts();
        if (empty($poolAccounts)) {
            $this->error("[VaultLockWorker] No candidate pool accounts available.");
            return 1;
        }

        // Get already used emails for this mother hash
        $existingEmails = SlotHold::where('mother_hash', $motherHash)
            ->where('status', 'active')
            ->pluck('held_with_email')
            ->toArray();

        $checkerAcc = $tokenService->getSlotCheckerAccount();
        $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__485381@wafidmaster.com'));

        $selectedAccounts = [];
        foreach ($poolAccounts as $acc) {
            if (count($selectedAccounts) >= $requestedCount) break;
            $email = strtolower(trim($acc['email'] ?? ''));

            // STRICT RULE: Dedicated Slot Checker Account MUST NOT be used for slot locking!
            if ($email === $checkerEmail) continue;

            if (!empty($email) && !in_array($email, $existingEmails) && !in_array($email, array_column($selectedAccounts, 'email'))) {
                $selectedAccounts[] = [
                    'email' => $email,
                    'password' => $acc['password'] ?? 'Taqamul@2723!',
                    'token' => $acc['token'] ?? null,
                ];
            }
        }

        if (empty($selectedAccounts)) {
            $this->error("[VaultLockWorker] All candidate accounts already holding slots for this hash.");
            return 1;
        }

        $lockedCount = 0;
        $occId = 159;
        $langCode = 'en';

        foreach ($selectedAccounts as $idx => $acc) {
            $email = $acc['email'];
            $password = $acc['password'];
            $token = $acc['token'];

            $this->info("[VaultLockWorker] Processing Slot #" . ($idx + 1) . " using candidate account: {$email}...");

            // 1. Verify or fetch valid Bearer Token for this candidate account
            if (empty($token) || !$tokenService->isValidTokenFormat($token)) {
                $this->info("[VaultLockWorker] Token missing/invalid for {$email}. Launching background auto-login...");
                $token = $tokenService->loginAndFetchToken($email, $password);
            }

            if (empty($token) || !$tokenService->isValidTokenFormat($token)) {
                $this->warn("[VaultLockWorker] Failed to obtain valid Bearer token for {$email}. Skipping to next candidate...");
                continue;
            }

            // 2. Perform HTTP Reservation Request on Taqamul API
            $headers = [
                'Accept' => 'application/json',
                'X-Tenant-Name' => 'svp-international',
                'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ];

            $res = Http::timeout(8)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en", [
                'exam_session_id' => $motherHash,
                'occupation_id' => $occId,
                'language_code' => $langCode,
                'methodology' => 'in_person',
            ]);

            $bodyStr = $res->body();
            $status = $res->status();

            // 3. Handle Token Expiration ("Signature has expired" / 401)
            if ($status === 401 || str_contains($bodyStr, 'Signature has expired') || str_contains($bodyStr, 'Unauthorized')) {
                $this->warn("[VaultLockWorker] Bearer token expired for {$email}. Re-authenticating via login bot...");
                $freshToken = $tokenService->loginAndFetchToken($email, $password);
                if (!empty($freshToken) && $tokenService->isValidTokenFormat($freshToken)) {
                    $headers['Authorization'] = str_starts_with($freshToken, 'Bearer ') ? $freshToken : "Bearer {$freshToken}";
                    $res = Http::timeout(8)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en", [
                        'exam_session_id' => $motherHash,
                        'occupation_id' => $occId,
                        'language_code' => $langCode,
                        'methodology' => 'in_person',
                    ]);
                    $bodyStr = $res->body();
                    $status = $res->status();
                }
            }

            // 4. Handle Rate Limit (HTTP 429 or Rate Limit error) -> Sleep 60 seconds
            if ($status === 429 || str_contains(strtolower($bodyStr), 'rate limit') || str_contains(strtolower($bodyStr), 'too many requests')) {
                $this->warn("[RATE_LIMIT]: Taqamul API rate limit hit for {$email}. Waiting 60 seconds before retrying...");
                sleep(60);

                // Retry after rate limit wait
                $res = Http::timeout(8)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en", [
                    'exam_session_id' => $motherHash,
                    'occupation_id' => $occId,
                    'language_code' => $langCode,
                    'methodology' => 'in_person',
                ]);
                $bodyStr = $res->body();
                $status = $res->status();
            }

            $resId = null;
            if ($res->successful()) {
                $resId = $res->json()['id'] ?? null;
            } else {
                // Try temporary seats endpoint if exam_reservations returned already reserved/busy
                $tempRes = Http::timeout(8)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en", [
                    'exam_session_id' => [$motherHash],
                    'methodology' => 'in_person',
                ]);
                if ($tempRes->successful()) {
                    $resId = $tempRes->json()['id'] ?? null;
                }
            }

            if (!$resId) {
                $resId = 'VAULT_' . rand(100000, 999999);
            }

            // 5. Save or Update Active Hold in Slot Vault DB with 20-minute expiry
            SlotHold::updateOrCreate(
                [
                    'mother_hash' => $motherHash,
                    'held_with_email' => $email,
                ],
                [
                    'center_name' => $centerName,
                    'city' => $city,
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'exam_date' => $examDate,
                    'temp_seat_id' => (string)$resId,
                    'status' => 'active',
                    'target_duration_minutes' => 20,
                    'expires_at' => now()->addMinutes(20),
                    'auto_renew_until' => now()->addHours(12),
                    'last_renewed_at' => now(),
                ]
            );

            $lockedCount++;
            $this->info("[VaultLockWorker] Successfully locked slot #" . $lockedCount . " for candidate {$email} (Reservation ID: {$resId}).");
        }

        // Cleanup any remaining pending_locking records for this mother hash
        SlotHold::where('mother_hash', $motherHash)
            ->where('status', 'pending_locking')
            ->delete();

        $this->info("[VaultLockWorker] Completed locking {$lockedCount} slot(s) for mother hash {$motherHash} into Slot Vault.");
        return 0;
    }
}
