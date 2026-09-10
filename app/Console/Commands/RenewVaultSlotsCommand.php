<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SlotHold;
use App\Models\Setting;
use App\Services\ProxyService;
use App\Services\TaqamulTokenService;
use Exception;

class RenewVaultSlotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vault:renew-slots {--daemon : Run continuously as high-frequency monitoring daemon} {--interval=2 : Polling interval in seconds for daemon mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zero-Latency Unlimited Slot Auto-Renew Engine: 3-Minute Hot-Standby Pre-Warm, Candidate Account Rotation, Instant Hand-off & Failure Logging';

    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';

    /**
     * Execute the console command.
     */
    public function handle(TaqamulTokenService $tokenService)
    {
        @set_time_limit(0);

        $isDaemon = (bool)$this->option('daemon');
        $interval = max(1, (int)$this->option('interval'));

        if ($isDaemon) {
            $this->info("==================================================================");
            $this->info(" ⚡ TAQAMUL ZERO-LATENCY UNLIMITED SLOT AUTO-RENEW DAEMON");
            $this->info(" Polling Interval: {$interval} second(s)");
            $this->info(" Pre-Warm Window: 3 Minutes before 20-min Expiry (Hot Standby)");
            $this->info(" Candidate Rotation: Unlimited Rotations Across Candidate Pool");
            $this->info(" Proxy Load Balancing: Active Across Multi-Account & Decodo Ports");
            $this->info(" Status: ACTIVE & MONITORING");
            $this->info("==================================================================");
        }

        do {
            try {
                $this->runRenewalCycle($tokenService);
            } catch (Exception $e) {
                $this->error("[VaultAutoRenew] Exception in renewal cycle: " . $e->getMessage());
                Log::error("[VaultAutoRenew] Exception in renewal cycle: " . $e->getMessage());
            }

            if ($isDaemon) {
                sleep($interval);
            }
        } while ($isDaemon);

        return 0;
    }

    /**
     * Run a single renewal and pre-warming cycle
     */
    public function runRenewalCycle(TaqamulTokenService $tokenService): void
    {
        // -----------------------------------------------------------------------------------------
        // STEP 1: PRE-WARM CANDIDATE ACCOUNTS 3 MINUTES BEFORE 20-MIN EXPIRY (T - 3 MINUTES)
        // -----------------------------------------------------------------------------------------
        // Identify all active holds where remaining time is <= 3 minutes (expires_at <= now() + 3 min)
        // and prewarm is not yet completed. Proactively log in the NEXT candidate account from the pool
        // so that its Bearer token is 100% active, warm, and ready on HOT STANDBY!
        $nearingHolds = SlotHold::where('status', 'active')
            ->where('expires_at', '<=', now()->addMinutes(3))
            ->where('expires_at', '>', now())
            ->get();

        if ($nearingHolds->isNotEmpty()) {
            $poolAccounts = $tokenService->getPoolAccounts();
            $checkerAcc = $tokenService->getSlotCheckerAccount();
            $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__259939@wafidmaster.com'));

            // Emails currently busy holding active seats
            $busyEmails = SlotHold::where('status', 'active')
                ->pluck('held_with_email')
                ->map(fn($e) => strtolower(trim($e)))
                ->unique()
                ->toArray();

            // Emails already pre-warmed for other nearing holds
            $prewarmedEmails = SlotHold::where('status', 'active')
                ->where('prewarm_status', 'ready')
                ->whereNotNull('next_candidate_email')
                ->pluck('next_candidate_email')
                ->map(fn($e) => strtolower(trim($e)))
                ->unique()
                ->toArray();

            foreach ($nearingHolds as $nearHold) {
                // If this hold is already pre-warmed and token is verified, skip
                if ($nearHold->prewarm_status === 'ready' && !empty($nearHold->next_candidate_token) && !empty($nearHold->next_candidate_email)) {
                    continue;
                }

                $currentEmail = strtolower(trim($nearHold->held_with_email));
                $targetCandidate = null;

                // ROTATION RULE: Select the NEXT candidate account from the pool that:
                // 1. Is NOT the current candidate (to bypass Taqamul's 3-4 reservation limit per account!)
                // 2. Is NOT the dedicated slot checker account
                // 3. Is NOT holding any other active seat
                // 4. Is NOT already pre-warmed for another nearing seat
                // Prioritize accounts that ALREADY have an active valid Bearer token for instant hand-off!
                foreach ($poolAccounts as $pAcc) {
                    $pEmail = strtolower(trim($pAcc['email'] ?? ''));
                    if (empty($pEmail)) continue;
                    if ($pEmail === $checkerEmail) continue;
                    if ($pEmail === $currentEmail) continue;
                    if (in_array($pEmail, $busyEmails)) continue;
                    if (in_array($pEmail, $prewarmedEmails)) continue;

                    $tok = $pAcc['token'] ?? null;
                    if (!empty($tok) && $tokenService->isValidTokenFormat($tok)) {
                        $targetCandidate = [
                            'email' => $pEmail,
                            'password' => $pAcc['password'] ?? 'Taqamul@2723!',
                            'token' => $tok,
                        ];
                        break;
                    }
                }

                if (!$targetCandidate) {
                    foreach ($poolAccounts as $pAcc) {
                        $pEmail = strtolower(trim($pAcc['email'] ?? ''));
                        if (empty($pEmail)) continue;
                        if ($pEmail === $checkerEmail) continue;
                        if ($pEmail === $currentEmail) continue;
                        if (in_array($pEmail, $busyEmails)) continue;
                        if (in_array($pEmail, $prewarmedEmails)) continue;

                        $targetCandidate = [
                            'email' => $pEmail,
                            'password' => $pAcc['password'] ?? 'Taqamul@2723!',
                            'token' => $pAcc['token'] ?? null,
                        ];
                        break;
                    }
                }

                // If pool is tight, allow fallback to any available non-current candidate or current candidate
                if (!$targetCandidate) {
                    foreach ($poolAccounts as $pAcc) {
                        $pEmail = strtolower(trim($pAcc['email'] ?? ''));
                        if (!empty($pEmail) && $pEmail !== $checkerEmail && !in_array($pEmail, $prewarmedEmails)) {
                            $targetCandidate = [
                                'email' => $pEmail,
                                'password' => $pAcc['password'] ?? 'Taqamul@2723!',
                                'token' => $pAcc['token'] ?? null,
                            ];
                            break;
                        }
                    }
                }

                if (!$targetCandidate) {
                    // Last resort: pre-warm the current candidate so token is fresh
                    $targetCandidate = [
                        'email' => $currentEmail,
                        'password' => $tokenService->getPasswordForAccount($currentEmail),
                        'token' => $tokenService->getTokenForAccount($currentEmail),
                    ];
                }

                $candidateEmail = $targetCandidate['email'];
                $candidatePass = $targetCandidate['password'];
                $candidateToken = $targetCandidate['token'];

                $remainingSec = $nearHold->expires_at ? now()->diffInSeconds($nearHold->expires_at, false) : 0;
                $remMin = floor($remainingSec / 60);
                $remSec = $remainingSec % 60;

                $this->info("[VaultAutoRenew ⚡] Pre-warming next candidate {$candidateEmail} for seat {$nearHold->temp_seat_id} (Expires in {$remMin}m {$remSec}s)...");

                // Execute proactive fast check or HTTP login to obtain 100% fresh Bearer token
                $freshToken = null;
                if (!empty($candidateToken) && $tokenService->isValidTokenFormat($candidateToken)) {
                    $freshToken = $candidateToken;
                }

                if (empty($freshToken)) {
                    $freshToken = $tokenService->loginAndFetchTokenHttp($candidateEmail, $candidatePass, null, false);
                    if (empty($freshToken) || !$tokenService->isValidTokenFormat($freshToken)) {
                        $freshToken = $tokenService->loginAndFetchToken($candidateEmail, $candidatePass);
                    }
                }

                if (!empty($freshToken) && $tokenService->isValidTokenFormat($freshToken)) {
                    $tokenService->updateAccountToken($candidateEmail, $freshToken);
                    $nearHold->update([
                        'next_candidate_email' => $candidateEmail,
                        'next_candidate_token' => $freshToken,
                        'prewarm_status' => 'ready',
                        'prewarmed_at' => now(),
                    ]);
                    $prewarmedEmails[] = $candidateEmail;
                    $this->info("[VaultAutoRenew 🔑] HOT STANDBY READY: Candidate {$candidateEmail} is 100% authenticated & waiting for zero-latency hand-off at expiration!");
                } else {
                    $this->warn("[VaultAutoRenew ⚠️] Could not pre-warm token for candidate {$candidateEmail}. Will attempt on-the-fly renewal.");
                }
            }
        }

        // -----------------------------------------------------------------------------------------
        // STEP 2: INSTANT ZERO-LATENCY REBOOKING AT 20-MIN EXPIRY (expires_at <= now())
        // -----------------------------------------------------------------------------------------
        $expiringHolds = SlotHold::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiringHolds->isEmpty()) {
            return;
        }

        $this->info("[VaultAutoRenew 🚀] Found " . $expiringHolds->count() . " active slot hold(s) at 20-min expiry. Executing instant hand-off...");

        $expiringHoldsGrouped = $expiringHolds->groupBy('mother_hash');
        $renewedCount = 0;
        $expandedCount = 0;

        foreach ($expiringHoldsGrouped as $motherHash => $holds) {
            $sampleHold = $holds->first();

            // Check if this mother_hash set is undergoing its first renewal (any active hold has renew_count == 0)
            $allHoldsForHash = SlotHold::where('mother_hash', $motherHash)->where('status', 'active')->get();
            $minRenewCount = $allHoldsForHash->min('renew_count');
            $hasFirstRenewCycle = ($minRenewCount === 0 || $minRenewCount === null);

            foreach ($holds as $hold) {
                $categoryId = $hold->category_id ?: 159;
                $occId = 2061;
                $langCode = 'LOABB';
                if ($categoryId == 160) {
                    $occId = 2062;
                    $langCode = 'ar';
                } elseif ($categoryId == 59) {
                    $occId = 2018;
                    $langCode = 'TLRBB';
                }

                $resUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en";
                $resPayload = [
                    'exam_session_id' => $motherHash,
                    'occupation_id' => $occId,
                    'language_code' => $langCode,
                    'methodology' => 'in_person',
                ];

                // 1. Determine Candidate Account for this renewal cycle
                // Priority: Use pre-warmed candidate (3 minutes ahead) for zero-latency instant booking!
                $rebookEmail = null;
                $rebookToken = null;

                if ($hold->prewarm_status === 'ready' && !empty($hold->next_candidate_token) && !empty($hold->next_candidate_email)) {
                    $rebookEmail = $hold->next_candidate_email;
                    $rebookToken = $hold->next_candidate_token;
                    $this->info("[VaultAutoRenew ⚡] Using pre-warmed candidate {$rebookEmail} on hot-standby!");
                } else {
                    // On-the-fly candidate rotation if pre-warm was missed
                    $poolAccounts = $tokenService->getPoolAccounts();
                    $checkerAcc = $tokenService->getSlotCheckerAccount();
                    $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__259939@wafidmaster.com'));
                    $busyEmails = SlotHold::where('status', 'active')
                        ->where('id', '!=', $hold->id)
                        ->pluck('held_with_email')
                        ->map(fn($e) => strtolower(trim($e)))
                        ->unique()
                        ->toArray();

                    // Pass 1: Prioritize pool accounts with active valid tokens for instant hand-off
                    foreach ($poolAccounts as $pAcc) {
                        $pEmail = strtolower(trim($pAcc['email'] ?? ''));
                        if (empty($pEmail) || $pEmail === $checkerEmail || in_array($pEmail, $busyEmails)) continue;
                        if ($pEmail === strtolower(trim($hold->held_with_email))) continue; // rotate away from previous candidate

                        $tok = $pAcc['token'] ?? null;
                        if (!empty($tok) && $tokenService->isValidTokenFormat($tok)) {
                            $rebookEmail = $pEmail;
                            $rebookToken = $tok;
                            break;
                        }
                    }

                    // Pass 2: Fallback to other accounts and login if needed
                    if (empty($rebookEmail) || empty($rebookToken)) {
                        foreach ($poolAccounts as $pAcc) {
                            $pEmail = strtolower(trim($pAcc['email'] ?? ''));
                            if (empty($pEmail) || $pEmail === $checkerEmail || in_array($pEmail, $busyEmails)) continue;
                            if ($pEmail === strtolower(trim($hold->held_with_email))) continue;

                            $rebookEmail = $pEmail;
                            $rebookToken = $pAcc['token'] ?? null;
                            if (empty($rebookToken) || !$tokenService->isValidTokenFormat($rebookToken)) {
                                $rebookToken = $tokenService->loginAndFetchTokenHttp($pEmail, $pAcc['password'] ?? 'Taqamul@2723!', null, false);
                            }
                            if (!empty($rebookToken)) break;
                        }
                    }

                    if (empty($rebookEmail) || empty($rebookToken)) {
                        $rebookEmail = $hold->held_with_email;
                        $rebookToken = $tokenService->getTokenForAccount($rebookEmail);
                    }
                }

                if (empty($rebookToken)) {
                    $failureReason = "No Bearer token available for candidate {$rebookEmail}";
                    $this->error("[VaultAutoRenew ❌] {$failureReason}. Skipping renewal.");
                    Log::error("[VaultAutoRenew ❌] {$failureReason} for Mother Hash {$motherHash}");
                    $hold->recordFailure($failureReason, $rebookEmail, 401);
                    continue;
                }

                // 2. High-Scale Load-Balanced Dispatcher with Decodo port sharding
                $dispatchWithProxy = function(string $url, array $payload, string $token) {
                    $hdrs = [
                        'Accept' => 'application/json',
                        'X-Tenant-Name' => 'svp-international',
                        'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    ];

                    return ProxyService::executeWithLoadBalancedRetry(function($proxyOpts, $proxyInfo) use ($url, $payload, $hdrs) {
                        $res = Http::withoutVerifying()->timeout(10)->withOptions($proxyOpts)->withHeaders($hdrs)->post($url, $payload);
                        if ($res) {
                            Setting::checkAndHandleProxyFailure($res->status(), $res->body());
                        }
                        return $res;
                    }, 3, 20000);
                };

                // 3. Proactively release previous candidate's seat on Taqamul if needed so seat is cleanly freed
                $oldSeatId = $hold->temp_seat_id;
                if (!empty($oldSeatId) && !str_starts_with((string)$oldSeatId, 'VAULT_') && !str_starts_with((string)$oldSeatId, 'HOLD_')) {
                    try {
                        $delToken = $tokenService->getTokenForAccount($hold->held_with_email) ?: $rebookToken;
                        $delHdrs = [
                            'Accept' => 'application/json',
                            'X-Tenant-Name' => 'svp-international',
                            'Authorization' => str_starts_with($delToken, 'Bearer ') ? $delToken : "Bearer {$delToken}",
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        ];
                        Http::withoutVerifying()->timeout(4)->withHeaders($delHdrs)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$oldSeatId}?locale=en");
                        Http::withoutVerifying()->timeout(4)->withHeaders($delHdrs)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats/{$oldSeatId}?locale=en");
                    } catch (Exception $e) {}
                }

                // 4. INSTANT REBOOKING: Lock fresh seat using rotated candidate account
                $res = $dispatchWithProxy($resUrl, $resPayload, $rebookToken);
                $status = $res ? $res->status() : 0;
                $bodyStr = $res ? $res->body() : 'No response from Taqamul API';

                $newResId = null;
                $resData = null;

                if ($res && $res->successful()) {
                    $resData = $res->json();
                    $newResId = $resData['id'] ?? ($resData['data']['id'] ?? ($resData['reservation']['id'] ?? null));
                }

                // Secondary fallback check via temporary_seats if direct reservation returned 422
                if (empty($newResId)) {
                    try {
                        $tempUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en";
                        $tempPayload = [
                            'exam_session_id' => [$motherHash],
                            'methodology' => 'in_person',
                        ];
                        $tempRes = $dispatchWithProxy($tempUrl, $tempPayload, $rebookToken);
                        if ($tempRes && $tempRes->successful()) {
                            $tempData = $tempRes->json();
                            $sessHash = $tempData['exam_session_id'] ?? $motherHash;
                            $res2 = $dispatchWithProxy($resUrl, [
                                'exam_session_id' => $sessHash,
                                'occupation_id' => $occId,
                                'language_code' => $langCode,
                                'methodology' => 'in_person',
                            ], $rebookToken);
                            if ($res2 && $res2->successful()) {
                                $resData = $res2->json();
                                $newResId = $resData['id'] ?? ($resData['data']['id'] ?? ($resData['reservation']['id'] ?? null));
                            }
                        }
                    } catch (Exception $e) {}
                }

                // 5. SUCCESS: Update DB with Rotated Candidate, Fresh Reservation ID, and +20 Min Expiry
                if (!empty($newResId) && !str_starts_with((string)$newResId, 'VAULT_')) {
                    $session = $resData['exam_session'] ?? ($resData['data']['exam_session'] ?? []);
                    $tc = $resData['test_center'] ?? ($resData['data']['test_center'] ?? ($session['test_center'] ?? []));
                    $newCenterName = $tc['test_center_name'] ?? ($tc['name'] ?? null);
                    $newCity = $tc['test_center_city'] ?? ($tc['city'] ?? null);

                    $newRenewCount = $hold->renew_count + 1;
                    $updateFields = [
                        'held_with_email' => $rebookEmail,
                        'temp_seat_id' => (string)$newResId,
                        'renew_count' => $newRenewCount,
                        'expires_at' => now()->addMinutes(20),
                        'auto_renew_until' => now()->addHours(72), // Continuous unlimited auto-renew
                        'last_renewed_at' => now(),
                        'next_candidate_email' => null,
                        'next_candidate_token' => null,
                        'prewarm_status' => 'idle',
                        'last_failure_reason' => null,
                        'last_failure_at' => null,
                    ];

                    if (!empty($newCenterName)) $updateFields['center_name'] = $newCenterName;
                    if (!empty($newCity)) $updateFields['city'] = $newCity;

                    $hold->update($updateFields);
                    $hold->recordSeatHistory((string)$newResId, $newRenewCount, "Rotated Auto-Renew (#{$newRenewCount})", [
                        'email' => $rebookEmail,
                    ]);

                    $renewedCount++;
                    $this->info("[VaultAutoRenew ✅] SUCCESS: Rotated seat to candidate {$rebookEmail}! (New Res ID: {$newResId}, Renew Count: {$newRenewCount}, Expires: " . $updateFields['expires_at']->format('h:i:s A') . "). UNLIMITED HOLD MAINTAINED.");
                    Log::info("[VaultAutoRenew ✅] Mother Hash {$motherHash} successfully renewed to {$rebookEmail} (Res ID: {$newResId}, Cycle: {$newRenewCount})");

                } else {
                    // 6. DETAILED FAILURE LOGGING & IMMEDIATE CANDIDATE RESCUE FALLBACK
                    $errDetail = '';
                    $decoded = json_decode($bodyStr, true);
                    if (isset($decoded['errors'])) {
                        $errDetail = is_array($decoded['errors']) ? json_encode($decoded['errors']) : (string)$decoded['errors'];
                    } elseif (isset($decoded['message'])) {
                        $errDetail = (string)$decoded['message'];
                    } else {
                        $errDetail = substr(strip_tags($bodyStr), 0, 160);
                    }

                    $failureMsg = "HTTP {$status}: " . ($errDetail ?: 'Reservation rejected by Taqamul');
                    $this->error("[VaultAutoRenew ❌] FAILED to renew seat for mother hash {$motherHash} using candidate {$rebookEmail}. {$failureMsg}");
                    Log::error("[VaultAutoRenew ❌] Rebooking FAILED for {$motherHash} with candidate {$rebookEmail}: {$failureMsg}. Full Response: {$bodyStr}");
                    $hold->recordFailure($failureMsg, $rebookEmail, $status);

                    // RESCUE FALLBACK: Try other candidate accounts from pool immediately so seat is not lost!
                    $this->warn("[VaultAutoRenew 🔄] Attempting immediate RESCUE FALLBACK with another pool candidate account...");
                    $poolAccounts = $tokenService->getPoolAccounts();
                    $rescued = false;

                    // Pass 1: Prioritize accounts with already-valid tokens for instant zero-latency recovery
                    foreach ($poolAccounts as $fallbackAcc) {
                        $fallbackEmail = strtolower(trim($fallbackAcc['email'] ?? ''));
                        if (empty($fallbackEmail) || $fallbackEmail === $checkerEmail || $fallbackEmail === strtolower($rebookEmail)) continue;

                        $fallbackToken = $fallbackAcc['token'] ?? null;
                        if (!empty($fallbackToken) && $tokenService->isValidTokenFormat($fallbackToken)) {
                            $resRescue = $dispatchWithProxy($resUrl, $resPayload, $fallbackToken);
                            if ($resRescue && $resRescue->successful()) {
                                $rData = $resRescue->json();
                                $rescueResId = $rData['id'] ?? ($rData['data']['id'] ?? ($rData['reservation']['id'] ?? null));
                                 if (!empty($rescueResId)) {
                                     $newRenewCount = $hold->renew_count + 1;
                                     $hold->update([
                                         'held_with_email' => $fallbackEmail,
                                         'temp_seat_id' => (string)$rescueResId,
                                         'renew_count' => $newRenewCount,
                                         'expires_at' => now()->addMinutes(20),
                                         'auto_renew_until' => now()->addHours(72),
                                         'last_renewed_at' => now(),
                                         'next_candidate_email' => null,
                                         'next_candidate_token' => null,
                                         'prewarm_status' => 'idle',
                                         'last_failure_reason' => null,
                                         'last_failure_at' => null,
                                     ]);
                                     $hold->recordSeatHistory((string)$rescueResId, $newRenewCount, 'Auto-Renewed (Rescue)', $fallbackEmail, 'success');
                                     $this->info("[VaultAutoRenew 🛡️] RESCUED seat with candidate {$fallbackEmail}! (New Res ID: {$rescueResId}, Renew Count: {$newRenewCount})");
                                     Log::info("[VaultAutoRenew 🛡️] Mother Hash {$motherHash} rescued with candidate {$fallbackEmail} (Res ID: {$rescueResId})");
                                     $rescued = true;
                                     $renewedCount++;
                                     break;
                                 }
                            }
                        }
                    }

                    if (!$rescued) {
                        $this->error("[VaultAutoRenew 🚨] RESCUE FAILED: Could not save seat for mother hash {$motherHash} across fallback candidates.");
                    }
                }
            }

            // -----------------------------------------------------------------------------------------
            // STEP 3: FIRST-RENEWAL AUTO-EXPANSION (Lock +1 additional seat during FIRST renewal cycle)
            // -----------------------------------------------------------------------------------------
            if ($hasFirstRenewCycle && $sampleHold) {
                try {
                    $poolAccounts = $tokenService->getPoolAccounts();
                    $checkerAcc = $tokenService->getSlotCheckerAccount();
                    $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__259939@wafidmaster.com'));

                    $busyEmails = SlotHold::where('status', 'active')
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

                    if ($idleCandidate) {
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

                            $expUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en";
                            $expPayload = [
                                'exam_session_id' => $motherHash,
                                'occupation_id' => $occId,
                                'language_code' => $langCode,
                                'methodology' => 'in_person',
                            ];

                            $dispatchExp = function(string $url, array $payload, string $token) {
                                $hdrs = [
                                    'Accept' => 'application/json',
                                    'X-Tenant-Name' => 'svp-international',
                                    'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                ];

                                return ProxyService::executeWithLoadBalancedRetry(function($proxyOpts, $proxyInfo) use ($url, $payload, $hdrs) {
                                    $res = Http::withoutVerifying()->timeout(10)->withOptions($proxyOpts)->withHeaders($hdrs)->post($url, $payload);
                                    if ($res) {
                                        Setting::checkAndHandleProxyFailure($res->status(), $res->body());
                                    }
                                    return $res;
                                }, 3, 20000);
                            };

                            $expRes = $dispatchExp($expUrl, $expPayload, $idleToken);

                            if (!$expRes || !$expRes->successful()) {
                                try {
                                    $tempExp = $dispatchExp("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en", [
                                        'exam_session_id' => [$motherHash],
                                        'methodology' => 'in_person',
                                    ], $idleToken);
                                    if ($tempExp && $tempExp->successful()) {
                                        $tempExpData = $tempExp->json();
                                        $sessHash = $tempExpData['exam_session_id'] ?? $motherHash;
                                        $expRes = $dispatchExp($expUrl, [
                                            'exam_session_id' => $sessHash,
                                            'occupation_id' => $occId,
                                            'language_code' => $langCode,
                                            'methodology' => 'in_person',
                                        ], $idleToken);
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
                                        'auto_renew_until' => now()->addHours(72),
                                        'last_renewed_at' => now(),
                                        'renew_count' => 1,
                                    ]);
                                    $newHold->recordSeatHistory((string)$expResId, 1, 'First Renewal Expansion (+1 Seat)', ['email' => $idleEmail]);
                                    $expandedCount++;
                                    $this->info("[VaultAutoRenew] First Renewal Expansion: Successfully locked +1 new seat #{$targetNewCount} for mother hash {$motherHash} using candidate {$idleEmail} (Reservation ID: {$expResId}).");
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->warn("[VaultAutoRenew] First Renewal Expansion exception: " . $e->getMessage());
                }
            }
        }

        if ($renewedCount > 0 || $expandedCount > 0) {
            $this->info("[VaultAutoRenew] Auto-renewal cycle completed: {$renewedCount} slot(s) renewed, {$expandedCount} extra seat(s) expanded.");
        }
    }
}
