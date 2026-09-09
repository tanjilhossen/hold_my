<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SlotHold;
use App\Services\TaqamulTokenService;
use Exception;

class RenewVaultSlotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vault:renew-slots';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-renew active slot holds near 20-minute expiry and re-login candidates if Bearer tokens expire';

    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';

    /**
     * Execute the console command.
     */
    public function handle(TaqamulTokenService $tokenService)
    {
        @set_time_limit(300);

        // STEP 1: PRE-WARM CANDIDATE BEARER TOKENS 3 MINUTES BEFORE 20-MIN EXPIRY
        // For any active hold where remaining time is <= 3 minutes (expires_at <= now() + 3 min),
        // proactively login via fast Decodo HTTP and refresh token in memory/cache so it's 100% warm!
        $nearingHolds = SlotHold::where('status', 'active')
            ->where('expires_at', '<=', now()->addMinutes(3))
            ->get();

        if ($nearingHolds->isNotEmpty()) {
            foreach ($nearingHolds as $nearHold) {
                $email = $nearHold->held_with_email;
                $password = $tokenService->getPasswordForAccount($email);
                $token = $tokenService->getTokenForAccount($email);

                if (empty($token) || !$tokenService->isValidTokenFormat($token)) {
                    $this->info("[VaultAutoRenew] Pre-warming fresh Bearer token for candidate {$email} (3-min pre-expiry window)...");
                    $freshToken = $tokenService->loginAndFetchTokenHttp($email, $password, null, true);
                    if (!empty($freshToken) && $tokenService->isValidTokenFormat($freshToken)) {
                        $tokenService->updateAccountToken($email, $freshToken);
                    }
                }
            }
        }

        // STEP 2: FIND ACTIVE HOLDS AT 20-MIN EXPIRY (expires_at <= now())
        $expiringHolds = SlotHold::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiringHolds->isEmpty()) {
            $this->info("[VaultAutoRenew] No active slot holds at 20-min expiry right now.");
            return 0;
        }

        $this->info("[VaultAutoRenew] Found " . $expiringHolds->count() . " active slot hold(s) at 20-min expiry. Re-locking fresh seats in seconds...");

        $expiringHoldsGrouped = $expiringHolds->groupBy('mother_hash');

        $renewedCount = 0;
        $expandedCount = 0;

        foreach ($expiringHoldsGrouped as $motherHash => $holds) {
            $lastResData = null;
            $sampleHold = $holds->first();

            // Check if this mother_hash set is undergoing its first renewal (any active hold has renew_count == 0)
            $allHoldsForHash = SlotHold::where('mother_hash', $motherHash)->where('status', 'active')->get();
            $minRenewCount = $allHoldsForHash->min('renew_count');
            $hasFirstRenewCycle = ($minRenewCount === 0 || $minRenewCount === null);

            foreach ($holds as $hold) {
                $email = $hold->held_with_email;
                $categoryId = $hold->category_id ?: 159;

                $this->info("[VaultAutoRenew] Re-locking fresh slot for candidate {$email} (Hash: " . substr($motherHash, 0, 12) . "...)...");

                // Fetch current token and password for candidate (pre-warmed token)
                $token = $tokenService->getTokenForAccount($email);
                $password = $tokenService->getPasswordForAccount($email);

                if (empty($token) || !$tokenService->isValidTokenFormat($token)) {
                    $this->warn("[VaultAutoRenew] Token missing/invalid for {$email}. Re-logging candidate via fast Decodo HTTP...");
                    $token = $tokenService->loginAndFetchTokenHttp($email, $password, null, true);
                    if (empty($token) || !$tokenService->isValidTokenFormat($token)) {
                        $token = $tokenService->loginAndFetchToken($email, $password);
                    }
                }

                if (empty($token)) {
                    $this->error("[VaultAutoRenew] Could not fetch token for {$email}. Skipping renewal.");
                    continue;
                }

                $occId = 2061;
                $langCode = 'LOABB';
                if ($categoryId == 160) {
                    $occId = 2062;
                    $langCode = 'ar';
                } elseif ($categoryId == 59) {
                    $occId = 2018;
                    $langCode = 'TLRBB';
                }

                $headers = [
                    'Accept' => 'application/json',
                    'X-Tenant-Name' => 'svp-international',
                    'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ];

                $dispatchReservation = function(string $url, array $payload, array $hdrs) {
                    $proxyCfg = \App\Models\Setting::getProxyConfig();
                    $baseOpts = ['curl' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]];

                    if (!empty($proxyCfg['proxy'])) {
                        $proxyOpts = $baseOpts;
                        \App\Services\ProxyService::applyProxyToOptions($proxyOpts);
                        try {
                            $res = Http::withoutVerifying()->timeout(10)->withOptions($proxyOpts)->withHeaders($hdrs)->post($url, $payload);
                            if ($res) {
                                \App\Models\Setting::checkAndHandleProxyFailure($res->status(), $res->body());
                                if ($res->status() < 500) {
                                    return $res;
                                }
                            }
                        } catch (Exception $e) {
                            \App\Models\Setting::checkAndHandleProxyFailure(0, '', $e->getMessage());
                        }
                    }

                    return Http::withoutVerifying()->timeout(10)->withOptions($baseOpts)->withHeaders($hdrs)->post($url, $payload);
                };

                $resPayload = [
                    'exam_session_id' => $motherHash,
                    'occupation_id' => $occId,
                    'language_code' => $langCode,
                    'methodology' => 'in_person',
                ];
                $resUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en";

                // STEP 1: Directly lock fresh seat upon 20-min expiration
                $res = $dispatchReservation($resUrl, $resPayload, $headers);
                $bodyStr = $res ? $res->body() : '';
                $status = $res ? $res->status() : 0;

                // STEP 2: Check if Token Expired (401) -> Re-login candidate & retry
                if ($status === 401 || str_contains($bodyStr, 'Signature has expired') || str_contains($bodyStr, 'Unauthorized')) {
                    $this->warn("[VaultAutoRenew] Token expired for {$email}. Re-logging candidate...");
                    $freshToken = $tokenService->loginAndFetchTokenHttp($email, $password, null, true);
                    if (empty($freshToken) || !$tokenService->isValidTokenFormat($freshToken)) {
                        $freshToken = $tokenService->loginAndFetchToken($email, $password);
                    }

                    if (!empty($freshToken) && $tokenService->isValidTokenFormat($freshToken)) {
                        $tokenService->updateAccountToken($email, $freshToken);
                        $headers['Authorization'] = str_starts_with($freshToken, 'Bearer ') ? $freshToken : "Bearer {$freshToken}";
                        $res = $dispatchReservation($resUrl, $resPayload, $headers);
                        $bodyStr = $res ? $res->body() : '';
                        $status = $res ? $res->status() : 0;
                    }
                }

                // STEP 3: Check Rate Limit (429) -> Wait 60s & retry
                if ($status === 429 || str_contains(strtolower($bodyStr), 'rate limit')) {
                    $this->warn("[RATE_LIMIT]: Rate limit hit for {$email}. Waiting 60s before retry...");
                    sleep(60);
                    $res = $dispatchReservation($resUrl, $resPayload, $headers);
                }

                $newResId = null;
                $newCenterName = null;
                $newCity = null;

                if ($res && $res->successful()) {
                    $lastResData = $res->json();
                    $newResId = $lastResData['id'] ?? ($lastResData['data']['id'] ?? ($lastResData['reservation']['id'] ?? null));
                }

                // STEP 4: Secondary fallback check via temporary_seats endpoint if direct reservation returned 422
                if (empty($newResId)) {
                    try {
                        $tempUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en";
                        $tempPayload = [
                            'exam_session_id' => [$motherHash],
                            'methodology' => 'in_person',
                        ];
                        $tempRes = $dispatchReservation($tempUrl, $tempPayload, $headers);
                        if ($tempRes && $tempRes->successful()) {
                            $tempData = $tempRes->json();
                            $sessHash = $tempData['exam_session_id'] ?? $motherHash;

                            $res2Payload = [
                                'exam_session_id' => $sessHash,
                                'occupation_id' => $occId,
                                'language_code' => $langCode,
                                'methodology' => 'in_person',
                            ];
                            $res2 = $dispatchReservation($resUrl, $res2Payload, $headers);
                            if ($res2 && $res2->successful()) {
                                $lastResData = $res2->json();
                                $newResId = $lastResData['id'] ?? ($lastResData['data']['id'] ?? ($lastResData['reservation']['id'] ?? null));
                            }
                        }
                    } catch (Exception $e) {}
                }

                // STEP 5: If initial attempts failed, trigger fresh re-login for candidate and retry reservation
                if (empty($newResId)) {
                    $this->warn("[VaultAutoRenew] Direct reservation failed for {$email}. Re-authenticating candidate and retrying...");
                    $freshToken = $tokenService->loginAndFetchTokenHttp($email, $password, null, true);
                    if (empty($freshToken) || !$tokenService->isValidTokenFormat($freshToken)) {
                        $freshToken = $tokenService->loginAndFetchToken($email, $password);
                    }

                    if (!empty($freshToken) && $tokenService->isValidTokenFormat($freshToken)) {
                        $tokenService->updateAccountToken($email, $freshToken);
                        $headers['Authorization'] = str_starts_with($freshToken, 'Bearer ') ? $freshToken : "Bearer {$freshToken}";
                        
                        $resRetry = $dispatchReservation($resUrl, $resPayload, $headers);
                        if ($resRetry && $resRetry->successful()) {
                            $lastResData = $resRetry->json();
                            $newResId = $lastResData['id'] ?? ($lastResData['data']['id'] ?? ($lastResData['reservation']['id'] ?? null));
                        }

                        if (empty($newResId)) {
                            // Retry via temporary_seats
                            try {
                                $tempResRetry = $dispatchReservation("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en", [
                                    'exam_session_id' => [$motherHash],
                                    'methodology' => 'in_person',
                                ], $headers);
                                if ($tempResRetry && $tempResRetry->successful()) {
                                    $resRetry2 = $dispatchReservation($resUrl, $resPayload, $headers);
                                    if ($resRetry2 && $resRetry2->successful()) {
                                        $lastResData = $resRetry2->json();
                                        $newResId = $lastResData['id'] ?? ($lastResData['data']['id'] ?? ($lastResData['reservation']['id'] ?? null));
                                    }
                                }
                            } catch (Exception $e) {}
                        }
                    }
                }

                // STEP 6: Update DB ONLY IF a REAL fresh reservation ID was generated by Taqamul
                if (!empty($newResId) && !str_starts_with((string)$newResId, 'VAULT_')) {
                    $session = $lastResData['exam_session'] ?? ($lastResData['data']['exam_session'] ?? []);
                    $tc = $lastResData['test_center'] ?? ($lastResData['data']['test_center'] ?? ($session['test_center'] ?? []));
                    
                    $newCenterName = $tc['test_center_name'] ?? ($tc['name'] ?? null);
                    $newCity = $tc['test_center_city'] ?? ($tc['city'] ?? null);

                    $updateFields = [
                        'temp_seat_id' => (string)$newResId,
                        'renew_count' => $hold->renew_count + 1,
                        'expires_at' => now()->addMinutes(20),
                        'last_renewed_at' => now(),
                    ];

                    if (!empty($newCenterName)) {
                        $updateFields['center_name'] = $newCenterName;
                    }
                    if (!empty($newCity)) {
                        $updateFields['city'] = $newCity;
                    }

                    $hold->update($updateFields);
                    $hold->fresh()->recordSeatHistory((string)$newResId, (int)$updateFields['renew_count'], 'Auto-Renewed');
                    $renewedCount++;
                    $this->info("[VaultAutoRenew] SUCCESS: Fresh seat reserved for {$email} (New Reservation ID: {$newResId}, Renew Count: {$updateFields['renew_count']}, Expires: " . $updateFields['expires_at']->format('h:i:s A') . ").");
                } else {
                    $this->error("[VaultAutoRenew] FAILED: Could not reserve fresh seat on Taqamul for candidate {$email}. Reservation rejected.");
                }
            }

            // FIRST-RENEWAL AUTO-EXPANSION: Lock +1 additional seat into table during FIRST renewal cycle for this mother hash set
            if ($hasFirstRenewCycle) {
                try {
                    $poolAccounts = $tokenService->getPoolAccounts();
                    $checkerAcc = $tokenService->getSlotCheckerAccount();
                    $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__136281@wafidmaster.com'));

                    $busyEmails = SlotHold::activeOrPending()
                        ->pluck('held_with_email')
                        ->map(fn($e) => strtolower(trim($e)))
                        ->unique()
                        ->toArray();

                    $idleCandidate = null;
                    foreach ($poolAccounts as $pAcc) {
                        $pEmail = strtolower(trim($pAcc['email'] ?? ''));
                        if (empty($pEmail)) continue;
                        if ($pEmail === $checkerEmail) continue;
                        if (in_array($pEmail, $busyEmails)) continue;

                        $idleCandidate = [
                            'email' => $pEmail,
                            'password' => $pAcc['password'] ?? 'Taqamul@2723!',
                            'token' => $pAcc['token'] ?? null,
                        ];
                        break;
                    }

                    if ($idleCandidate && $sampleHold) {
                        $idleEmail = $idleCandidate['email'];
                        $idlePassword = $idleCandidate['password'];
                        $idleToken = $idleCandidate['token'];

                        $currentTableCount = SlotHold::where('mother_hash', $motherHash)->where('status', 'active')->count();
                        $targetNewCount = $currentTableCount + 1;

                        $this->info("[VaultAutoRenew] First Renewal Expansion: Attempting to lock seat #{$targetNewCount} for mother hash {$motherHash} using idle candidate {$idleEmail}...");

                        if (empty($idleToken) || !$tokenService->isValidTokenFormat($idleToken)) {
                            $idleToken = $tokenService->loginAndFetchTokenHttp($idleEmail, $idlePassword, null, true);
                            if (empty($idleToken) || !$tokenService->isValidTokenFormat($idleToken)) {
                                $idleToken = $tokenService->loginAndFetchToken($idleEmail, $idlePassword);
                            }
                        }

                        if (!empty($idleToken) && $tokenService->isValidTokenFormat($idleToken)) {
                            $occId = 2061;
                            $langCode = 'LOABB';
                            $catId = $sampleHold->category_id ?: 159;
                            if ($catId == 160) {
                                $occId = 2062;
                                $langCode = 'ar';
                            } elseif ($catId == 59) {
                                $occId = 2018;
                                $langCode = 'TLRBB';
                            }

                            $expHeaders = [
                                'Accept' => 'application/json',
                                'X-Tenant-Name' => 'svp-international',
                                'Authorization' => str_starts_with($idleToken, 'Bearer ') ? $idleToken : "Bearer {$idleToken}",
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            ];

                            $expPayload = [
                                'exam_session_id' => $motherHash,
                                'occupation_id' => $occId,
                                'language_code' => $langCode,
                                'methodology' => 'in_person',
                            ];
                            $expUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en";

                            $expRes = $dispatchReservation($expUrl, $expPayload, $expHeaders);
                            
                            if (!$expRes || !$expRes->successful()) {
                                try {
                                    $tempExp = $dispatchReservation("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en", [
                                        'exam_session_id' => [$motherHash],
                                        'methodology' => 'in_person',
                                    ], $expHeaders);
                                    if ($tempExp && $tempExp->successful()) {
                                        $tempExpData = $tempExp->json();
                                        $sessHash = $tempExpData['exam_session_id'] ?? $motherHash;
                                        $expRes = $dispatchReservation($expUrl, [
                                            'exam_session_id' => $sessHash,
                                            'occupation_id' => $occId,
                                            'language_code' => $langCode,
                                            'methodology' => 'in_person',
                                        ], $expHeaders);
                                    }
                                } catch (Exception $e) {}
                            }

                            if ($expRes && $expRes->successful()) {
                                $expData = $expRes->json();
                                $expResId = $expData['id'] ?? ($expData['data']['id'] ?? ($expData['reservation']['id'] ?? null));

                                if (!empty($expResId)) {
                                    $newHold = SlotHold::create([
                                        'mother_hash' => $motherHash,
                                        'held_with_email' => $idleEmail,
                                        'center_name' => $sampleHold->center_name,
                                        'city' => $sampleHold->city,
                                        'category_id' => $sampleHold->category_id,
                                        'category_name' => $sampleHold->category_name,
                                        'exam_date' => $sampleHold->exam_date,
                                        'temp_seat_id' => (string)$expResId,
                                        'status' => 'active',
                                        'target_duration_minutes' => 20,
                                        'expires_at' => now()->addMinutes(20),
                                        'auto_renew_until' => now()->addHours(24),
                                        'last_renewed_at' => now(),
                                        'renew_count' => 1,
                                    ]);
                                    $newHold->recordSeatHistory((string)$expResId, 1, 'First Renewal Expansion (+1 Seat)');
                                    $expandedCount++;
                                    $this->info("[VaultAutoRenew] First Renewal Expansion: Successfully locked +1 new seat #{$targetNewCount} for mother hash {$motherHash} using candidate {$idleEmail} (Reservation ID: {$expResId}).");
                                }
                            } else {
                                $this->info("[VaultAutoRenew] First Renewal Expansion: Could not expand +1 seat for mother hash {$motherHash} (Taqamul HTTP " . ($expRes ? $expRes->status() : 0) . " or full).");
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->warn("[VaultAutoRenew] First Renewal Expansion exception: " . $e->getMessage());
                }
            }
        }

        $this->info("[VaultAutoRenew] Auto-renewal cycle completed for {$renewedCount} slot hold(s) with {$expandedCount} extra seat(s) expanded.");
        return 0;
    }
}
