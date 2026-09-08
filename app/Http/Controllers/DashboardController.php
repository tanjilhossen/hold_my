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

        $recentHolds = \App\Models\SlotHold::where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->take(6)
            ->get();

        $stats = [
            'active_holds' => $activeHoldsCount,
            'vault_items' => $vaultItemsCount,
            'pool_accounts' => count($poolAccounts),
            'active_tokens' => $activeTokenCount,
            'system_status' => 'Online & Auto-Renewing'
        ];

        return view('dashboard', compact('stats', 'recentHolds'));
    }
}
