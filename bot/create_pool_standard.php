<?php
require 'E:/taqamul/web/vendor/autoload.php';
$app = require 'E:/taqamul/web/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Passenger;
use App\Models\Setting;
use App\Services\TaqamulTokenService;
use App\Services\WafidMailService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

$targetCount = 1000;
$passportSrc = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PASSPORT.jpg";
$photoSrc = "C:\\Users\\WALTON\\OneDrive\\Desktop\\New folder (6)\\PHOTO.jpg";

if (!file_exists($passportSrc) || !file_exists($photoSrc)) {
    echo "❌ Error: Passport or Photo file not found in Desktop folder.\n";
    exit(1);
}

echo "========================================================================\n";
echo "  🌟 STANDARD CANDIDATE POOL GENERATOR (/register-candidate flow)\n";
echo "  🎯 Target Accounts: {$targetCount}\n";
echo "========================================================================\n\n";

$firstNames = ['MD', 'MOHAMMED', 'ABDUL', 'KAZI', 'SHEIKH', 'AL'];
$lastNames = ['RAHMAN', 'ISLAM', 'HOSSAIN', 'AHMED', 'KARIM', 'ALI', 'KHAN', 'CHOWDHURY', 'HASAN', 'UDDIN', 'MIAH', 'ALAM'];
$prefix4Letters = ['BGDA', 'BGDB', 'BGDC', 'BGDE', 'BDKA', 'BDKB', 'BMEA', 'BMET', 'DHKP', 'CTGQ'];

$wafidService = app(WafidMailService::class);
$tokenService = app(TaqamulTokenService::class);

$successCount = 0;

for ($i = 1; $i <= $targetCount; $i++) {
    $fn = $firstNames[array_rand($firstNames)];
    $ln = $lastNames[array_rand($lastNames)];
    $prefix = $prefix4Letters[array_rand($prefix4Letters)];
    $passportNo = $prefix . rand(100000, 999999);
    $randNum = rand(100000, 999999);
    $cleanFirst = strtolower(preg_replace('/[^a-z0-9]/', '', $fn));
    $cleanLast = strtolower(preg_replace('/[^a-z0-9]/', '', $ln));
    $mailboxName = "pool_{$cleanFirst}{$cleanLast}_{$randNum}";
    $email = "{$mailboxName}@renonx.tech";
    $password = "Taqamul@" . rand(1000, 9999) . "!";

    $dob = rand(1991, 1999) . "-" . sprintf("%02d", rand(1, 12)) . "-" . sprintf("%02d", rand(1, 28));
    $exp = rand(2031, 2035) . "-" . sprintf("%02d", rand(1, 12)) . "-" . sprintf("%02d", rand(1, 28));

    echo "------------------------------------------------------------------------\n";
    echo "🚀 [{$i}/{$targetCount}] Creating Candidate: {$fn} {$ln}\n";
    echo "   Email: {$email} | Passport: {$passportNo} | Pass: {$password}\n";
    echo "------------------------------------------------------------------------\n";

    // 1. Create mailbox on Wafid mail server
    try {
        $wafidService->createMailbox($mailboxName);
    } catch (Exception $e) {}

    // 2. Store Passport & Photo in public storage
    $passportStorageName = 'passengers/passports/pool_' . uniqid() . '.jpg';
    $photoStorageName = 'passengers/photos/pool_' . uniqid() . '.jpg';
    Storage::disk('public')->put($passportStorageName, file_get_contents($passportSrc));
    Storage::disk('public')->put($photoStorageName, file_get_contents($photoSrc));

    // 3. Create Passenger Record
    $passenger = Passenger::create([
        'user_id' => 1,
        'first_name' => $fn,
        'last_name' => $ln,
        'no_last_name' => false,
        'passport_number' => $passportNo,
        'national_id' => '199' . rand(100000000, 999999999),
        'gender' => 'male',
        'date_of_birth' => $dob,
        'passport_expiration_date' => $exp,
        'country_id' => '1',
        'country_name' => 'Bangladesh',
        'nationality_id' => '1',
        'nationality_name' => 'Bangladeshi',
        'passport_file_path' => $passportStorageName,
        'personal_photo_path' => $photoStorageName,
        'education_level' => 'no_educational_qualification',
        'experience_level' => 'no_experience',
        'institute_name' => 'Dhaka Technical Institute',
        'email' => $email,
        'password' => $password,
        'country_code' => '+880',
        'phone_number' => '17' . rand(10000000, 99999999),
        'preferable_contact' => 'email',
        'status' => 'processing',
    ]);

    echo "⚡ Executing standard registration bot (taqamul:process-passenger {$passenger->id})...\n";

    // 4. Run standard registration command in isolated shell
    $cmd = "\"D:\\xampp\\php\\php.exe\" \"E:\\taqamul\\web\\artisan\" taqamul:process-passenger {$passenger->id} 2>&1";
    $output = shell_exec($cmd);
    echo $output . "\n";

    $passenger->refresh();

    if ($passenger->status === 'AC Done') {
        echo "🎉 Account registration completed on Taqamul! Fetching live Bearer Token...\n";
        
        // Fetch Bearer Token
        $token = $tokenService->loginAndFetchToken($passenger->email, $passenger->password);
        
        // 5. Add directly to Candidate Pool (slot_checker_pool_accounts)
        $pool = $tokenService->getPoolAccounts();
        $pool[] = [
            'id' => time() . rand(10, 99),
            'name' => $passenger->full_name,
            'email' => $passenger->email,
            'password' => $passenger->password,
            'token' => $token ?: null,
            'token_expires_at' => date('Y-m-d H:i:s', strtotime('+48 hours')),
            'status' => $token ? 'active' : 'idle'
        ];
        $tokenService->savePoolAccounts($pool);

        $successCount++;
        echo "✅ [{$i}/{$targetCount}] SUCCESS: {$passenger->full_name} ({$passenger->email}) ADDED TO CANDIDATE POOL! (Pool Total: " . count($pool) . ")\n\n";
    } else {
        echo "⚠️ [{$i}/{$targetCount}] Registration status: {$passenger->status} | Error: {$passenger->error_message}\n\n";
    }

    sleep(1);
}

echo "========================================================================\n";
echo "  🎉 COMPLETED! {$successCount}/{$targetCount} accounts created and added to Candidate Pool!\n";
echo "========================================================================\n";
