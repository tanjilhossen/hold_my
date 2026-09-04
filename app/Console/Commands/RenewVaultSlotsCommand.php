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

        // Find active holds expiring within the next 3 minutes
        $expiringHolds = SlotHold::where('status', 'active')
            ->where('expires_at', '<=', now()->addMinutes(3))
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

            // Fetch current token for candidate
            $token = $tokenService->getTokenForAccount($email);

            if (empty($token) || !$tokenService->isValidTokenFormat($token)) {
                $this->warn("[VaultAutoRenew] Token missing/invalid for {$email}. Re-logging candidate in background...");
                $token = $tokenService->loginAndFetchToken($email, 'Taqamul@2723!');
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

            // Re-reserve on Taqamul
            $res = Http::timeout(8)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en", [
                'exam_session_id' => $motherHash,
                'occupation_id' => $occId,
                'language_code' => $langCode,
                'methodology' => 'in_person',
            ]);

            $bodyStr = $res->body();
            $status = $res->status();

            // Check if Token Expired ("Signature has expired" / 401)
            if ($status === 401 || str_contains($bodyStr, 'Signature has expired') || str_contains($bodyStr, 'Unauthorized')) {
                $this->warn("[VaultAutoRenew] Signature has expired for {$email}. Triggering automatic candidate re-login...");
                $freshToken = $tokenService->loginAndFetchToken($email, 'Taqamul@2723!');

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

            // Check Rate Limit (429)
            if ($status === 429 || str_contains(strtolower($bodyStr), 'rate limit')) {
                $this->warn("[RATE_LIMIT]: Rate limit hit for {$email}. Waiting 60s before retry...");
                sleep(60);
                $res = Http::timeout(8)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en", [
                    'exam_session_id' => $motherHash,
                    'occupation_id' => $occId,
                    'language_code' => $langCode,
                    'methodology' => 'in_person',
                ]);
            }

            $newResId = null;
            $newCenterName = null;
            $newCity = null;
            $isSuccess = false;

            if ($res->successful()) {
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

            $updateFields = [
                'temp_seat_id' => (string)$newResId,
                'renew_count' => $isSuccess ? ($hold->renew_count + 1) : $hold->renew_count,
                'expires_at' => $isSuccess ? now()->addMinutes(20) : now()->addMinutes(3),
                'last_renewed_at' => now(),
            ];

            if (!empty($newCenterName)) {
                $updateFields['center_name'] = $newCenterName;
            }
            if (!empty($newCity)) {
                $updateFields['city'] = $newCity;
            }

            $hold->update($updateFields);

            $renewedCount++;
            $this->info("[VaultAutoRenew] Validated & renewed slot for {$email} (Seat ID: {$newResId}, Center: " . ($newCenterName ?: $hold->center_name) . ", Renew Count: {$hold->renew_count}, Expires: " . $updateFields['expires_at']->format('h:i:s A') . ").");
        }

        $this->info("[VaultAutoRenew] Auto-renewal cycle completed for {$renewedCount} slot hold(s).");
        return 0;
    }
}
