<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $tokenService = app(\App\Services\TaqamulTokenService::class);
        $poolAccounts = $tokenService->getPoolAccounts();
        
        $activeTokenCount = 0;
        foreach ($poolAccounts as $acc) {
            $token = $acc['token'] ?? null;
            if (!empty($token) && $tokenService->isValidTokenFormat($token)) {
                $activeTokenCount++;
            }
        }

        $activeHoldsCount = \App\Models\SlotHold::where('status', 'active')->count();
        $vaultItemsCount = \App\Models\SlotHold::where('status', 'active')
            ->distinct('mother_hash')
            ->count('mother_hash');

        $activeHolds = \App\Models\SlotHold::where('status', 'active')->orderBy('created_at', 'desc')->get();

        $groupedVaultHolds = [];
        foreach ($activeHolds as $hold) {
            $hash = $hold->mother_hash;
            if (!isset($groupedVaultHolds[$hash])) {
                $groupedVaultHolds[$hash] = [
                    'mother_hash' => $hash,
                    'center_name' => $hold->center_name ?? 'Test Center',
                    'city' => $hold->city ?? 'N/A',
                    'category_name' => $hold->category_name ?? 'Profession',
                    'exam_date' => $hold->exam_date ? date('Y-m-d', strtotime($hold->exam_date)) : 'N/A',
                    'total_locked' => 0,
                ];
            }
            $groupedVaultHolds[$hash]['total_locked']++;
        }
        $groupedVaultHolds = array_values($groupedVaultHolds);

        $stats = [
            'active_holds' => $activeHoldsCount,
            'vault_items' => $vaultItemsCount,
            'pool_accounts' => count($poolAccounts),
            'active_tokens' => $activeTokenCount,
            'system_status' => 'Online & Auto-Renewing'
        ];

        return view('dashboard', compact('stats', 'groupedVaultHolds'));
    }
}
