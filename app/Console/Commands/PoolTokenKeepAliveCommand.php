<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TaqamulTokenService;

class PoolTokenKeepAliveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taqamul:keep-alive-pool {--batch=3 : Max batch size for auto-login re-authentications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send keep-alive heartbeats to candidate pool tokens and auto-refresh expired ones in background';

    /**
     * Execute the console command.
     */
    public function handle(TaqamulTokenService $tokenService)
    {
        $this->info('[Taqamul Keep-Alive] Running candidate pool token health check & heartbeat...');
        
        $health = $tokenService->keepAlivePoolTokens();
        $this->info("Pool Health: {$health['active']}/{$health['total']} accounts active ({$health['expired']} expired/missing).");

        $maxBatch = (int) $this->option('batch');
        if ($health['expired'] > 0) {
            $this->info("[Taqamul Keep-Alive] Triggering background auto-login for up to {$maxBatch} expired account(s)...");
            $refresh = $tokenService->refreshExpiredPoolTokens($maxBatch);
            $this->info($refresh['message']);
        } else {
            $this->info('[Taqamul Keep-Alive] All candidate pool tokens are 100% active and fresh!');
        }

        return 0;
    }
}
