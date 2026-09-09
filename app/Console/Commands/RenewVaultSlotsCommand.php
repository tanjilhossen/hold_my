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

        // Find active holds expiring within the next 1 minute (19 minutes held uninterrupted)
        $expiringHolds = SlotHold::where('status', 'active')
            ->where('expires_at', '<=', now()->addMinutes(1))
            ->get();

        if ($expiringHolds->isEmpty()) {
            $this->info("[VaultAutoRenew] No active slot holds near 20-min expiry right now.");
            return 0;
        }

        $this->info("[VaultAutoRenew] Found " . $expiringHolds->count() . " active slot hold(s) nearing expiry. Initiating auto-renewal...");

        $renewedCount = 0;
        foreach ($expiringHolds as $hold) {
            $email = $hold->held_with_email;
            $motherHash = $hold->mother_hash;
            $categoryId = $hold->category_id ?: 159;

            $this->info("[VaultAutoRenew] Renewing slot hold for candidate {$email} (Hash: " . substr($motherHash, 0, 12) . "...)...");

            // Fetch current token and password for candidate
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

            $proxyCfg = \App\Models\Setting::getProxyConfig();
            $httpOpts = [
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]
            ];
            if (!empty($proxyCfg['proxy'])) {
                $httpOpts['proxy'] = $proxyCfg['proxy'];
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
                    $proxyOpts = array_merge($baseOpts, ['proxy' => $proxyCfg['proxy']]);
                    try {
                        $res = Http::withoutVerifying()->timeout(10)->withOptions($proxyOpts)->withHeaders($hdrs)->post($url, $payload);
                        if ($res->status() < 500) {
                            return $res;
                        }
                    } catch (Exception $e) {}
                }

                return Http::withoutVerifying()->timeout(10)->withOptions($baseOpts)->withHeaders($hdrs)->post($url, $payload);
            };

            $oldSeatId = $hold->temp_seat_id;
            $resPayload = [
                'exam_session_id' => $motherHash,
                'occupation_id' => $occId,
                'language_code' => $langCode,
                'methodology' => 'in_person',
            ];
            $resUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en";

            // STEP 1: Attempt new reservation FIRST without releasing old seat to keep slot 100% held on Taqamul
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

            // STEP 3: If Taqamul requires releasing old seat first (422), release old seat & re-reserve immediately
            if (!$res || !$res->successful()) {
                if (!empty($oldSeatId) && is_numeric($oldSeatId)) {
                    try {
                        $delOpts = ['curl' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]];
                        Http::withoutVerifying()->timeout(4)->withOptions($delOpts)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$oldSeatId}?locale=en");
                        Http::withoutVerifying()->timeout(3)->withOptions($delOpts)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats/{$oldSeatId}?locale=en");
                    } catch (Exception $e) {}
                }

                $res = $dispatchReservation($resUrl, $resPayload, $headers);
                $bodyStr = $res ? $res->body() : '';
                $status = $res ? $res->status() : 0;
            }

            // STEP 4: Check Rate Limit (429)
            if ($status === 429 || str_contains(strtolower($bodyStr), 'rate limit')) {
                $this->warn("[RATE_LIMIT]: Rate limit hit for {$email}. Waiting 60s before retry...");
                sleep(60);
                $res = $dispatchReservation($resUrl, $resPayload, $headers);
            }

            $newResId = null;
            $newCenterName = null;
            $newCity = null;
            $isSuccess = false;

            if ($res && $res->successful()) {
                $resData = $res->json();
                $newResId = $resData['id'] ?? ($resData['data']['id'] ?? null);
                
                $session = $resData['exam_session'] ?? ($resData['data']['exam_session'] ?? []);
                $tc = $resData['test_center'] ?? ($resData['data']['test_center'] ?? ($session['test_center'] ?? []));
                
                $newCenterName = $tc['test_center_name'] ?? ($tc['name'] ?? null);
                $newCity = $tc['test_center_city'] ?? ($tc['city'] ?? null);

                if (!empty($newResId)) {
                    $isSuccess = true;
                }
            }

            if (empty($newResId) || str_starts_with((string)$newResId, 'VAULT_')) {
                $newResId = $hold->temp_seat_id;
            }

            // Update DB with fresh 20-minute expiration
            $updateFields = [
                'temp_seat_id' => (string)$newResId,
                'renew_count' => $isSuccess ? ($hold->renew_count + 1) : max(1, $hold->renew_count + 1),
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

            // Clean up old seat ID ONLY AFTER new seat ID is saved in DB
            if ($isSuccess && !empty($oldSeatId) && is_numeric($oldSeatId) && (string)$oldSeatId !== (string)$newResId) {
                try {
                    $delOpts = ['curl' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]];
                    Http::withoutVerifying()->timeout(3)->withOptions($delOpts)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$oldSeatId}?locale=en");
                } catch (Exception $e) {}
            }

            if (!empty($newResId)) {
                $hold->fresh()->recordSeatHistory((string)$newResId, (int)$updateFields['renew_count'], 'Auto-Renewed');
            }

            $renewedCount++;
            $this->info("[VaultAutoRenew] Validated & renewed slot for {$email} (Seat ID: {$newResId}, Center: " . ($newCenterName ?: $hold->center_name) . ", Renew Count: {$updateFields['renew_count']}, Expires: " . $updateFields['expires_at']->format('h:i:s A') . ").");
        }

        $this->info("[VaultAutoRenew] Auto-renewal cycle completed for {$renewedCount} slot hold(s).");
        return 0;
    }
}
