<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Admin\SlotCheckerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SlotSniperDaemonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taqamul:snipe-slots {--city= : Target city name} {--category=160 : Target category ID} {--interval=1 : Polling interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zero-Latency High-Frequency Slot Sniper Daemon: Continuously monitors and auto-grabs open slots instantly';

    /**
     * Execute the console command.
     */
    public function handle(SlotCheckerController $controller)
    {
        $city = $this->option('city') ?: 'Dhaka';
        $categoryId = (int) ($this->option('category') ?: 160);
        $intervalSeconds = (int) ($this->option('interval') ?: 1);

        $this->info("==================================================================");
        $this->info(" ⚡ TAQAMUL ZERO-LATENCY HIGH-FREQUENCY SLOT SNIPER DAEMON ");
        $this->info(" Target City: {$city}");
        $this->info(" Target Category ID: {$categoryId}");
        $this->info(" Polling Interval: {$intervalSeconds} second(s)");
        $this->info(" Status: ACTIVE & MONITORING");
        $this->info("==================================================================");

        Cache::put('slot_sniper_active', true, 3600);

        while (Cache::get('slot_sniper_active', true)) {
            try {
                $req = new Request();
                $req->merge([
                    'category_id' => $categoryId,
                    'city' => $city,
                ]);

                $res = $controller->batchScanAvailableSlots($req);
                $data = json_decode($res->getContent(), true);

                $totalSlots = $data['total_slots'] ?? 0;
                $timestamp = date('H:i:s');

                if ($totalSlots > 0 && !empty($data['dates_grouped'])) {
                    $this->info("[{$timestamp}] 🎯 TARGET SPOTTED! Found {$totalSlots} open slot(s) across " . count($data['dates_grouped']) . " date(s) in {$city}!");
                    
                    // Auto-trigger parallel swarm grab
                    $slotsToHold = [];
                    foreach ($data['dates_grouped'] as $dg) {
                        $dDate = $dg['date'];
                        if (!empty($dg['centers'])) {
                            foreach ($dg['centers'] as $c) {
                                $hash = $c['mother_hash'] ?? $c['session_id'] ?? null;
                                if ($hash && empty($c['already_held'])) {
                                    for ($k = 0; $k < 10; $k++) {
                                        $slotsToHold[] = [
                                            'mother_hash' => $hash,
                                            'center_name' => $c['center_name'],
                                            'city' => $city,
                                            'category_id' => $categoryId,
                                            'category_name' => $data['category_name'] ?? 'Worker',
                                            'exam_date' => $dDate,
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($slotsToHold)) {
                        $this->info("[{$timestamp}] ⚡ LAUNCHING PARALLEL SWARM GRAB (" . count($slotsToHold) . " seat hold calls)...");
                        $holdReq = new Request();
                        $holdReq->merge([
                            'slots' => $slotsToHold,
                            'duration_minutes' => 120,
                        ]);

                        $holdRes = $controller->bulkHoldCenterSlots($holdReq);
                        $holdData = json_decode($holdRes->getContent(), true);
                        $this->info("[{$timestamp}] 🟢 SWARM RESULT: " . ($holdData['message'] ?? 'Hold completed!'));
                    }
                } else {
                    $this->line("[{$timestamp}] Monitoring {$city}... (0 open slots detected)");
                }

            } catch (\Exception $e) {
                $this->error("Sniper Error: " . $e->getMessage());
            }

            sleep($intervalSeconds);
        }

        $this->info("Sniper daemon stopped.");
        return 0;
    }
}
