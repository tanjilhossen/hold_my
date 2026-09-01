<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TaqamulTokenService;
use App\Http\Controllers\Admin\SlotCheckerController;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class SlotEngineApiController extends Controller
{
    protected TaqamulTokenService $tokenService;
    protected SlotCheckerController $slotController;

    public function __construct(TaqamulTokenService $tokenService, SlotCheckerController $slotController)
    {
        $this->tokenService = $tokenService;
        $this->slotController = $slotController;
    }

    /**
     * Verify API Secret Key Header
     */
    protected function authenticateEngineRequest(Request $request): bool
    {
        $providedKey = $request->header('X-Engine-Secret-Key') ?: $request->input('api_secret');
        $configuredKey = Setting::get('slot_engine_secret_key', env('SLOT_ENGINE_SECRET_KEY', 'taqamul_secret_engine_key_2026'));

        return !empty($providedKey) && hash_equals($configuredKey, $providedKey);
    }

    /**
     * Endpoint 1: Engine Node Handshake & Health Status
     */
    public function handshake(Request $request)
    {
        if (!$this->authenticateEngineRequest($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Invalid Engine Secret Key'], 401);
        }

        $poolAccounts = $this->tokenService->getPoolAccounts();
        $health = $this->tokenService->keepAlivePoolTokens();

        return response()->json([
            'success' => true,
            'node' => [
                'name' => Setting::get('slot_engine_node_name', 'Default Slot Engine VPS Node'),
                'ip' => $request->ip(),
                'status' => 'ONLINE',
                'pool_accounts_total' => count($poolAccounts),
                'pool_accounts_active' => $health['active'],
                'pool_accounts_expired' => $health['expired'],
                'server_time' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Endpoint 2: Execute Remote Bulk Slot Holding
     */
    public function executeRemoteHold(Request $request)
    {
        if (!$this->authenticateEngineRequest($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Invalid Engine Secret Key'], 401);
        }

        $slots = $request->input('slots', []);
        $durationMinutes = (int) $request->input('duration_minutes', 120);

        if (empty($slots) || !is_array($slots)) {
            return response()->json(['success' => false, 'message' => 'No slots payload provided.'], 422);
        }

        Log::info('[SlotEngineApi] Remote hold request received for ' . count($slots) . ' slot targets.');

        $holdReq = new Request();
        $holdReq->merge([
            'slots' => $slots,
            'duration_minutes' => $durationMinutes,
        ]);

        return $this->slotController->bulkHoldCenterSlots($holdReq);
    }

    /**
     * Endpoint 3: Run Remote Scan on Saudi Taqamul API
     */
    public function executeRemoteScan(Request $request)
    {
        if (!$this->authenticateEngineRequest($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Invalid Engine Secret Key'], 401);
        }

        $categoryId = (int) $request->input('category_id', 160);
        $city = trim($request->input('city', 'Dhaka'));
        $targetDate = $request->input('date');

        if (!empty($targetDate)) {
            $scanReq = new Request();
            $scanReq->merge([
                'category_id' => $categoryId,
                'city' => $city,
                'date' => $targetDate,
            ]);
            return $this->slotController->scanSingleDateHold($scanReq);
        }

        $scanReq = new Request();
        $scanReq->merge([
            'category_id' => $categoryId,
            'city' => $city,
        ]);
        return $this->slotController->batchScanAvailableSlots($scanReq);
    }
}
