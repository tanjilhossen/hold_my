<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tokenService = new App\Services\TaqamulTokenService();
$token = $tokenService->getValidRoundRobinToken();

$headers = [
    'Accept' => 'application/json',
    'X-Tenant-Name' => 'svp-international',
    'Authorization' => "Bearer {$token}",
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
];

// Get sessions for Rajshahi 2026-09-05
$res = Illuminate\Support\Facades\Http::withHeaders($headers)->get("https://svp-international-api.pacc.sa/api/v1/individual_labor_space/exam_sessions", [
    'category_id' => 159,
    'city' => 'Rajshahi',
    'exam_date' => '2026-09-05',
    'locale' => 'en',
]);

echo "GET /exam_sessions Status: " . $res->status() . "\n";
$sessions = $res->json()['exam_sessions'] ?? [];

echo "Found " . count($sessions) . " sessions for 2026-09-05.\n\n";

foreach ($sessions as $idx => $s) {
    $hash = $s['id'];
    echo "--- Probing Hash #{$idx}: {$hash} ---\n";
    
    $probeRes = Illuminate\Support\Facades\Http::withHeaders($headers)->post("https://svp-international-api.pacc.sa/api/v1/individual_labor_space/exam_reservations?locale=en", [
        'exam_session_id' => $hash,
        'occupation_id' => 2061,
        'language_code' => 'LOABB',
        'methodology' => 'in_person',
    ]);

    echo "Probe HTTP Status: " . $probeRes->status() . "\n";
    $json = $probeRes->json();
    $resId = $json['id'] ?? null;
    $tcName = $json['test_center']['test_center_name'] ?? ($json['test_center']['name'] ?? ($json['exam_session']['test_center']['name'] ?? 'NOT FOUND'));
    echo "Extracted Center Name: {$tcName}\n";
    echo "Reservation ID Created: " . ($resId ? $resId : 'NONE') . "\n";

    if ($resId) {
        // Clear immediately
        $del = Illuminate\Support\Facades\Http::withHeaders($headers)->delete("https://svp-international-api.pacc.sa/api/v1/individual_labor_space/exam_reservations/{$resId}?locale=en");
        echo "Cancelled Reservation ID {$resId}: Status " . $del->status() . "\n";
    } else {
        echo "Error response: " . json_encode($json) . "\n";
    }
    echo "\n";
}
