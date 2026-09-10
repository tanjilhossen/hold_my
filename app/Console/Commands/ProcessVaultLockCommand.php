<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SlotHold;
use App\Models\Passenger;
use App\Models\Setting;
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
        @set_time_limit(900);
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

        // Priority Sort: Accounts with active & fresh Bearer tokens are processed FIRST!
        usort($poolAccounts, function ($a, $b) use ($tokenService) {
            $aScore = 0;
            if (!empty($a['token']) && $tokenService->isValidTokenFormat($a['token'])) {
                $aScore = (($a['status'] ?? '') === 'active') ? 2 : 1;
            }
            $bScore = 0;
            if (!empty($b['token']) && $tokenService->isValidTokenFormat($b['token'])) {
                $bScore = (($b['status'] ?? '') === 'active') ? 2 : 1;
            }
            return $bScore <=> $aScore;
        });

        $checkerAcc = $tokenService->getSlotCheckerAccount();
        $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__136281@wafidmaster.com'));

        $occId = 2061;
        $langCode = 'LOABB';
        if ($categoryId == 160) {
            $occId = 2062;
            $langCode = 'en';
        } elseif ($categoryId == 59) {
            $occId = 2018;
            $langCode = 'TLRBB';
        }

        $lockedCount = 0;
        $consecutive529Count = 0;
        $processedEmails = [];

        while ($lockedCount < $requestedCount) {
            // 1. FIRST check if there is an account pre-assigned in pending_locking for THIS mother hash
            $pendingHoldForThisHash = SlotHold::where('mother_hash', $motherHash)
                ->where('status', 'pending_locking')
                ->whereNotIn('held_with_email', $processedEmails)
                ->first();

            $candidate = null;
            if ($pendingHoldForThisHash) {
                $targetEmail = strtolower(trim($pendingHoldForThisHash->held_with_email));
                foreach ($poolAccounts as $pAcc) {
                    if (strtolower(trim($pAcc['email'] ?? '')) === $targetEmail) {
                        $candidate = [
                            'email' => $targetEmail,
                            'password' => $pAcc['password'] ?? 'Taqamul@2723!',
                            'token' => $pAcc['token'] ?? null,
                        ];
                        break;
                    }
                }
                if (!$candidate) {
                    $candidate = [
                        'email' => $targetEmail,
                        'password' => $tokenService->getPasswordForAccount($targetEmail),
                        'token' => $tokenService->getTokenForAccount($targetEmail),
                    ];
                }
            }

            // 2. If no pre-assigned candidate waiting, find next free candidate account from pool
            if (!$candidate) {
                // Get candidate emails busy in OTHER hashes or already active in this hash
                $busyEmails = SlotHold::where('mother_hash', '!=', $motherHash)
                    ->whereIn('status', ['active', 'pending_locking'])
                    ->pluck('held_with_email')
                    ->merge(
                        SlotHold::where('mother_hash', $motherHash)
                            ->where('status', 'active')
                            ->pluck('held_with_email')
                    )
                    ->map(fn($e) => strtolower(trim($e)))
                    ->unique()
                    ->toArray();

                foreach ($poolAccounts as $pAcc) {
                    $pEmail = strtolower(trim($pAcc['email'] ?? ''));
                    if (empty($pEmail)) continue;
                    if ($pEmail === $checkerEmail) continue; // Dedicated slot checker account MUST NOT be used for locking
                    if (in_array($pEmail, $busyEmails)) continue; // Skip accounts already holding active/pending seats elsewhere
                    if (in_array($pEmail, $processedEmails)) continue; // Skip accounts already attempted in this run

                    $candidate = [
                        'email' => $pEmail,
                        'password' => $pAcc['password'] ?? 'Taqamul@2723!',
                        'token' => $pAcc['token'] ?? null,
                    ];
                    break;
                }
            }

            if (!$candidate) {
                $this->warn("[VaultLockWorker] No more available candidate accounts in pool. Stopping at {$lockedCount}/{$requestedCount} locked slots.");
                break;
            }

            $email = $candidate['email'];
            $password = $candidate['password'];
            $token = $candidate['token'];
            $processedEmails[] = $email;

            $this->info("[VaultLockWorker] Processing Slot #" . ($lockedCount + 1) . " using candidate account: {$email}...");



            $headers = [
                'Accept' => 'application/json',
                'X-Tenant-Name' => 'svp-international',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ];

            $dispatchReservation = function(string $url, array $payload, array $hdrs) {
                return \App\Services\ProxyService::executeWithLoadBalancedRetry(function($proxyOpts, $proxyInfo) use ($url, $payload, $hdrs) {
                    $res = Http::withoutVerifying()->timeout(10)->withOptions($proxyOpts)->withHeaders($hdrs)->post($url, $payload);
                    if ($res) {
                        \App\Models\Setting::checkAndHandleProxyFailure($res->status(), $res->body());
                    }
                    return $res;
                }, 3, 20000);
            };

            $resId = null;
            $resSuccess = false;
            $status = 0;
            $bodyStr = '';

            $resPayload = [
                'exam_session_id' => $motherHash,
                'occupation_id' => $occId,
                'language_code' => $langCode,
                'methodology' => 'in_person',
            ];
            $resUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en";

            // STEP 1: If account already has an active Bearer token, attempt reservation IMMEDIATELY with existing token
            if (!empty($token) && $tokenService->isValidTokenFormat($token)) {
                $this->info("[VaultLockWorker] Attempting reservation with existing Bearer token for {$email}...");
                $headers['Authorization'] = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";

                try {
                    $res = $dispatchReservation($resUrl, $resPayload, $headers);
                    $bodyStr = $res->body();
                    $status = $res->status();

                    if ($res->successful()) {
                        $resData = $res->json();
                        $resId = $resData['id'] ?? ($resData['data']['id'] ?? ($resData['reservation']['id'] ?? null));
                        $resSuccess = true;
                    }
                } catch (\Exception $e) {
                    $status = 0;
                    $bodyStr = '';
                }
            } else {
                $status = 401;
                $bodyStr = 'No token';
            }

            // STEP 2: Handle 401 / Token Expired -> Trigger fresh auto-login & retry reservation ONCE
            if (!$resSuccess && ($status === 401 || str_contains($bodyStr, 'Signature has expired') || str_contains($bodyStr, 'Unauthorized') || empty($token))) {
                $this->warn("[VaultLockWorker] Token expired/missing for {$email}. Logging in freshly via Decodo IP...");
                $freshToken = $tokenService->loginAndFetchTokenHttp($email, $password, null, true);
                if (empty($freshToken) || !$tokenService->isValidTokenFormat($freshToken)) {
                    $freshToken = $tokenService->loginAndFetchToken($email, $password);
                }

                if (!empty($freshToken) && $tokenService->isValidTokenFormat($freshToken)) {
                    $tokenService->updateAccountToken($email, $freshToken);
                    $headers['Authorization'] = str_starts_with($freshToken, 'Bearer ') ? $freshToken : "Bearer {$freshToken}";

                    try {
                        $res = $dispatchReservation($resUrl, $resPayload, $headers);
                        $bodyStr = $res->body();
                        $status = $res->status();

                        if ($res->successful()) {
                            $resData = $res->json();
                            $resId = $resData['id'] ?? ($resData['data']['id'] ?? ($resData['reservation']['id'] ?? null));
                            $resSuccess = true;
                        }
                    } catch (\Exception $e) {}
                }
            }

            // STEP 3: Handle Rate Limit (429) -> Wait 60 seconds and retry
            if (!$resSuccess && ($status === 429 || str_contains(strtolower($bodyStr), 'rate limit') || str_contains(strtolower($bodyStr), 'too many requests'))) {
                $this->warn("[RATE_LIMIT]: Taqamul API rate limit hit for {$email}. Waiting 60 seconds before retrying...");
                sleep(60);

                try {
                    $res = $dispatchReservation($resUrl, $resPayload, $headers);

                    if ($res->successful()) {
                        $resData = $res->json();
                        $resId = $resData['id'] ?? ($resData['data']['id'] ?? ($resData['reservation']['id'] ?? null));
                        $resSuccess = true;
                    }
                } catch (\Exception $e) {}
            }

            // STEP 4: Secondary backup endpoint check
            if (!$resSuccess && !$resId) {
                try {
                    $tempUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en";
                    $tempPayload = [
                        'exam_session_id' => [$motherHash],
                        'methodology' => 'in_person',
                    ];
                    $tempRes = $dispatchReservation($tempUrl, $tempPayload, $headers);
                    if ($tempRes->successful()) {
                        $tempData = $tempRes->json();
                        $resId = $tempData['id'] ?? ($tempData['data']['id'] ?? null);
                        $resSuccess = true;
                    }
                } catch (\Exception $e) {}
            }

            // Check if slot was released by user while locking was in progress
            $existingHold = SlotHold::where('mother_hash', $motherHash)
                ->where('held_with_email', $email)
                ->first();

            if ($existingHold && $existingHold->status === 'released') {
                $this->warn("[VaultLockWorker] Slot for candidate {$email} was released by user during locking process. Canceling Taqamul reservation...");
                        $delOpts = ['curl' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]];
                        Http::withoutVerifying()->timeout(5)->withOptions($delOpts)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$resId}?locale=en");
                        Http::withoutVerifying()->timeout(5)->withOptions($delOpts)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats/{$resId}?locale=en");
                continue;
            }

            // STEP 5: If reservation failed completely for this candidate account -> clean up pending record & try next pool candidate
            if (!$resSuccess || !$resId) {
                if ($status === 404 || str_contains(strtolower($bodyStr), 'no longer available')) {
                    $this->error("[VaultLockWorker] Session hash {$motherHash} is NOT AVAILABLE on Taqamul (HTTP 404). Stopping locking and cleaning up pending records.");
                    Log::warning("[VaultLockWorker] Mother Hash {$motherHash} is not available (HTTP 404). Purged pending records.");
                    SlotHold::where('mother_hash', $motherHash)
                        ->where('status', 'pending_locking')
                        ->delete();
                    break;
                }

                if ($status === 529 || str_contains(strtolower($bodyStr), 'something went wrong') || str_contains(strtolower($bodyStr), 'full') || str_contains(strtolower($bodyStr), 'no seats')) {
                    $this->error("[VaultLockWorker] Session hash {$motherHash} is FULL or EXPIRED on Taqamul API. Stopping locking and cleaning up pending records.");
                    Log::warning("[VaultLockWorker] Mother Hash {$motherHash} is FULL/EXPIRED (HTTP {$status}). Purged pending records.");
                    SlotHold::where('mother_hash', $motherHash)
                        ->where('status', 'pending_locking')
                        ->delete();
                    break;
                }

                if ($status === 422 && (str_contains(strtolower($bodyStr), 'no test sessions') || str_contains(strtolower($bodyStr), 'seats') || str_contains(strtolower($bodyStr), 'occupation') || str_contains(strtolower($bodyStr), 'currently no'))) {
                    $this->error("[VaultLockWorker] Center {$centerName} has reached full capacity on Taqamul API (HTTP 422: No remaining seats). Stopping locking and cleaning up pending records.");
                    Log::info("[VaultLockWorker] Center {$centerName} ({$motherHash}) is fully booked on Taqamul. Cleaned up pending slots.");
                    SlotHold::where('mother_hash', $motherHash)
                        ->where('status', 'pending_locking')
                        ->delete();
                    break;
                }

                $this->warn("[VaultLockWorker] Failed to reserve seat for candidate {$email}. Cleaning up pending record & switching to next candidate account...");
                SlotHold::where('mother_hash', $motherHash)
                    ->where('held_with_email', $email)
                    ->where('status', 'pending_locking')
                    ->delete();
                continue;
            }

            $consecutive529Count = 0;

            // STEP 6: Save or Update Active Hold in Slot Vault DB with 20-minute expiry
            $activeHold = SlotHold::updateOrCreate(
                [
                    'mother_hash' => $motherHash,
                    'held_with_email' => $email,
                ],
                [
                    'center_name' => $centerName,
                    'city' => $city,
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'exam_date' => date('Y-m-d', strtotime($examDate)),
                    'temp_seat_id' => (string)$resId,
                    'status' => 'active',
                    'target_duration_minutes' => 20,
                    'expires_at' => now()->addMinutes(20),
                    'auto_renew_until' => now()->addHours(24),
                    'last_renewed_at' => now(),
                ]
            );

            if ($activeHold) {
                $activeHold->recordSeatHistory((string)$resId, max(1, $activeHold->renew_count), 'Initial Hold');
            }

            $lockedCount++;
            $this->info("[VaultLockWorker] Successfully locked slot #" . $lockedCount . " / {$requestedCount} for candidate {$email} (Reservation ID: {$resId}).");

            // DYNAMIC SEAT CALIBRATION: Read real live available_seats from Taqamul response payload
            if (isset($resData)) {
                $sessionData = $resData['exam_session'] ?? ($resData['data']['exam_session'] ?? []);
                if (isset($sessionData['available_seats']) && is_numeric($sessionData['available_seats'])) {
                    $remSeats = (int)$sessionData['available_seats'];
                    $totalCapacity = isset($sessionData['seats']) ? (int)$sessionData['seats'] : 10;
                    $actualAvailableAtStart = max(1, min($totalCapacity, $remSeats + $lockedCount));

                    if ($actualAvailableAtStart != $requestedCount) {
                        $this->info("[VaultLockWorker] Live Seat Calibration: Taqamul payload reveals total actual available seats = {$actualAvailableAtStart} (Original requested: {$requestedCount}). Adjusting target count to {$actualAvailableAtStart}.");
                        $requestedCount = $actualAvailableAtStart;

                        // Adjust pending_locking records to match actualAvailableAtStart
                        $neededPending = max(0, $requestedCount - $lockedCount);
                        $pendingHolds = SlotHold::where('mother_hash', $motherHash)->where('status', 'pending_locking')->get();

                        if ($pendingHolds->count() > $neededPending) {
                            $pendingHolds->slice($neededPending)->each->delete();
                        } elseif ($neededPending > $pendingHolds->count()) {
                            $extraNeeded = $neededPending - $pendingHolds->count();
                            $checkerAcc = $tokenService->getSlotCheckerAccount();
                            $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__259939@wafidmaster.com'));

                            $busyEmails = SlotHold::activeOrPending()
                                ->pluck('held_with_email')
                                ->map(fn($e) => strtolower(trim($e)))
                                ->unique()
                                ->toArray();

                            $candidateAccounts = $tokenService->getPoolAccounts();
                            foreach ($candidateAccounts as $acc) {
                                if ($extraNeeded <= 0) break;
                                $pEmail = strtolower(trim($acc['email'] ?? ''));
                                if (empty($pEmail) || $pEmail === $checkerEmail || in_array($pEmail, $busyEmails) || in_array($pEmail, $processedEmails)) continue;

                                SlotHold::updateOrCreate(
                                    [
                                        'mother_hash' => $motherHash,
                                        'held_with_email' => $pEmail,
                                    ],
                                    [
                                        'center_name' => $centerName,
                                        'city' => $city,
                                        'category_id' => $categoryId,
                                        'category_name' => $categoryName,
                                        'exam_date' => date('Y-m-d', strtotime($examDate)),
                                        'temp_seat_id' => 'Locking...',
                                        'status' => 'pending_locking',
                                        'target_duration_minutes' => 20,
                                        'expires_at' => now()->addMinutes(20),
                                        'auto_renew_until' => now()->addHours(24),
                                        'last_renewed_at' => now(),
                                    ]
                                );
                                $extraNeeded--;
                            }
                        }
                    }
                }
            }

            usleep(200000); // 0.2s brief pause
        }

        // Final cleanup of any remaining pending_locking records for this mother hash
        SlotHold::where('mother_hash', $motherHash)
            ->where('status', 'pending_locking')
            ->delete();

        $this->info("[VaultLockWorker] Completed locking {$lockedCount} slot(s) for mother hash {$motherHash} into Slot Vault.");
        return 0;
    }
}
