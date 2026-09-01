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

echo "Checking active reservations for pool__485381@wafidmaster.com...\n";

$res = Illuminate\Support\Facades\Http::withHeaders($headers)->get("https://svp-international-api.pacc.sa/api/v1/individual_labor_space/exam_reservations?locale=en");
echo "HTTP Status: " . $res->status() . "\n";

$data = $res->json();
print_r($data);

if (!empty($data['exam_reservations']) || !empty($data['data'])) {
    $items = $data['exam_reservations'] ?? ($data['data'] ?? []);
    foreach ($items as $item) {
        $id = $item['id'] ?? null;
        if ($id) {
            echo "Deleting active reservation ID: {$id}...\n";
            $delRes = Illuminate\Support\Facades\Http::withHeaders($headers)->delete("https://svp-international-api.pacc.sa/api/v1/individual_labor_space/exam_reservations/{$id}?locale=en");
            echo "Delete Status: " . $delRes->status() . "\n";
        }
    }
}
