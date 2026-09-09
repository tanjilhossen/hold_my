<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\SlotHold;
use App\Services\TaqamulTokenService;
use Exception;

class SlotVaultController extends Controller
{
    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';
    protected TaqamulTokenService $tokenService;

    public function __construct(TaqamulTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Display Slot Vault Interface
     */
    public function index()
    {
        return view('vault.index');
    }

    /**
     * AJAX Endpoint: Get Grouped Live Vault Data with 20-minute countdown timers
     */
    public function getVaultData()
    {
        // Non-blocking background auto-renew trigger if any active hold is nearing expiry (<= 1 min remaining)
        $hasExpiring = SlotHold::where('status', 'active')
            ->where('expires_at', '<=', now()->addMinutes(1))
            ->exists();

        if ($hasExpiring) {
            $lastTrigger = Cache::get('vault_last_auto_renew_trigger', 0);
            if (time() - $lastTrigger > 15) {
                Cache::put('vault_last_auto_renew_trigger', time(), 30);
                $this->triggerBackgroundAutoRenew();
            }
        }

        $allHolds = SlotHold::activeOrPending()->orderBy('created_at', 'desc')->get();

        $grouped = [];
        $activeCount = 0;

        foreach ($allHolds as $hold) {
            $hash = $hold->mother_hash;
            $slotRemaining = ($hold->status === 'active' && $hold->expires_at && $hold->expires_at->isFuture()) 
                ? now()->diffInSeconds($hold->expires_at) 
                : 0;

            if (!isset($grouped[$hash])) {
                $dbHash = \App\Models\SlotHash::where('mother_hash', $hash)->first();
                $startTime = $dbHash ? $dbHash->start_time : null;

                $grouped[$hash] = [
                    'mother_hash' => $hash,
                    'center_name' => $hold->center_name,
                    'city' => $hold->city,
                    'category_name' => $hold->category_name,
                    'exam_date' => $hold->exam_date ? $hold->exam_date->format('Y-m-d') : 'N/A',
                    'start_time' => $startTime ?: '09:30 AM',
                    'total_locked_slots' => 0,
                    'total_assigned_slots' => 0,
                    'is_locking_in_progress' => false,
                    'expires_at' => $hold->expires_at ? $hold->expires_at->toIso8601String() : null,
                    'remaining_seconds' => $slotRemaining,
                    'slots' => [],
                ];
            } else {
                if ($slotRemaining > $grouped[$hash]['remaining_seconds']) {
                    $grouped[$hash]['remaining_seconds'] = $slotRemaining;
                    $grouped[$hash]['expires_at'] = $hold->expires_at ? $hold->expires_at->toIso8601String() : null;
                }
            }

            $grouped[$hash]['total_assigned_slots']++;

            if ($hold->status === 'pending_locking') {
                $grouped[$hash]['is_locking_in_progress'] = true;
            } else {
                $activeCount++;
                $grouped[$hash]['total_locked_slots']++;
            }

            $historyList = $hold->seat_history ?: [];
            if (empty($historyList) && !empty($hold->temp_seat_id)) {
                $historyList = [
                    [
                        'renew_count' => max(1, $hold->renew_count),
                        'seat_id' => (string)$hold->temp_seat_id,
                        'timestamp' => $hold->created_at ? $hold->created_at->format('Y-m-d h:i:s A') : now()->format('Y-m-d h:i:s A'),
                        'type' => 'Initial Hold'
                    ]
                ];
            }

            $grouped[$hash]['slots'][] = [
                'id' => $hold->id,
                'email' => $hold->held_with_email,
                'temp_seat_id' => $hold->status === 'pending_locking' ? 'Processing...' : $hold->temp_seat_id,
                'status' => $hold->status,
                'expires_at' => ($hold->status === 'active' && $hold->expires_at) ? $hold->expires_at->format('h:i:s A') : 'Queued',
                'remaining_seconds' => $slotRemaining,
                'renew_count' => $hold->renew_count,
                'seat_history' => $historyList,
            ];
        }

        return response()->json([
            'success' => true,
            'count' => count($grouped),
            'total_active_holds' => $activeCount,
            'groups' => array_values($grouped),
        ]);
    }

    /**
     * AJAX Endpoint: Release a Single Candidate Slot
     */
    public function releaseSingleSlot(Request $request)
    {
        $request->validate([
            'hold_id' => 'required|integer',
        ]);

        $hold = SlotHold::find($request->input('hold_id'));
        if (!$hold) {
            return response()->json(['success' => false, 'message' => 'Hold record not found.'], 444);
        }

        $this->cancelReservationOnTaqamul($hold);
        $hold->update(['status' => 'released']);

        return response()->json([
            'success' => true,
            'message' => "Successfully released slot held by {$hold->held_with_email}.",
            'hold_id' => $hold->id,
        ]);
    }

    /**
     * AJAX Endpoint: Release All Slots under a Mother Hash Group
     */
    public function releaseGroupSlots(Request $request)
    {
        $request->validate([
            'mother_hash' => 'required|string',
        ]);

        $motherHash = trim($request->input('mother_hash'));
        $holds = SlotHold::where('mother_hash', $motherHash)->activeOrPending()->get();

        $releasedCount = 0;
        foreach ($holds as $hold) {
            $this->cancelReservationOnTaqamul($hold);
            $hold->update(['status' => 'released']);
            $releasedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully released all {$releasedCount} slot(s) for Mother Hash.",
            'released_count' => $releasedCount,
        ]);
    }

    /**
     * Helper: Cancel reservation or temporary seat hold on Taqamul API
     */
    protected function cancelReservationOnTaqamul(SlotHold $hold): void
    {
        if (empty($hold->temp_seat_id) || str_starts_with($hold->temp_seat_id, 'HOLD_')) {
            return;
        }

        $token = null;
        $accounts = $this->tokenService->getPoolAccounts();
        foreach ($accounts as $acc) {
            if (strtolower($acc['email'] ?? '') === strtolower($hold->held_with_email)) {
                $token = $acc['token'] ?? null;
                break;
            }
        }

        if (empty($token)) {
            $token = $this->tokenService->getSlotCheckerToken();
        }

        if (empty($token)) return;

        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-Name' => 'svp-international',
            'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ];

        try {
            Http::timeout(5)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$hold->temp_seat_id}?locale=en");
            Http::timeout(5)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats/{$hold->temp_seat_id}?locale=en");
        } catch (Exception $e) {
            Log::warning("Error releasing reservation {$hold->temp_seat_id} on Taqamul: " . $e->getMessage());
        }
    }

    /**
     * Dispatch vault:renew-slots in the background non-blockingly
     */
    protected function triggerBackgroundAutoRenew(): void
    {
        $artisanPath = base_path('artisan');
        $phpPath = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : 'php';
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "cmd /c start \"\" /B \"{$phpPath}\" -d extension=pdo_sqlite \"{$artisanPath}\" vault:renew-slots > NUL 2>&1";
            pclose(popen($cmd, "r"));
        } else {
            $escArtisan = escapeshellarg($artisanPath);
            $cmd = "nohup php {$escArtisan} vault:renew-slots > /dev/null 2>&1 &";
            pclose(popen($cmd, "r"));
        }
    }
}
