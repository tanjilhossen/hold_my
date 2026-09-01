<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tokenService = new App\Services\TaqamulTokenService();
$token = $tokenService->getSlotCheckerToken();

echo "Active Token Found: " . ($token ? 'YES' : 'NO') . "\n";
if ($token) {
    echo "Token preview: " . substr($token, 0, 60) . "...\n";
}
