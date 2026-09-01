<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting;
use App\Models\SlotHash;
use App\Models\SlotHold;
use App\Services\TaqamulTokenService;
use Exception;

class HoldSlotController extends Controller
{
    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';
    protected TaqamulTokenService $tokenService;

    public function __construct(TaqamulTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Prominent Bangladesh Cities List
     */
    protected array $cities = [
        'Dhaka',
        'Chittagong',
        'Sylhet',
        'Rajshahi',
        'Khulna',
        'Barisal',
        'Rangpur',
        'Mymensingh',
        'Cumilla',
        'Jessore',
        'Kushtia',
        'Bogra',
        'Dinajpur',
        'Tangail',
        'Faridpur',
        'Noakhali',
        'Narayanganj',
        'Gazipur',
        'Pabna',
    ];

    /**
     * Get Cached / Stored Occupations from Disk or API
     */
    public function getOccupations(): array
    {
        $diskFile = storage_path('app/taqamul_occupations_en.json');
        if (file_exists($diskFile)) {
            $data = json_decode(file_get_contents($diskFile), true);
            if (is_array($data) && count($data) > 0) {
                return $data;
            }
        }

        // Fallback default list
        return [
            [
                'id' => 2061,
                'name' => 'Load and Unload Worker',
                'category_id' => 159,
                'category' => ['id' => 159, 'english_name' => 'Load and unload workers', 'arabic_name' => 'عمال التحميل والتنزيل']
            ],
            [
                'id' => 2018,
                'name' => 'Tailor',
                'category_id' => 59,
                'category' => ['id' => 59, 'english_name' => 'Tailoring', 'arabic_name' => 'الخياطة']
            ],
            [
                'id' => 2045,
                'name' => 'Plumber (General)',
                'category_id' => 161,
                'category' => ['id' => 161, 'english_name' => 'Plumbing', 'arabic_name' => 'السباكة']
            ],
            [
                'id' => 2050,
                'name' => 'Electrician (Building)',
                'category_id' => 162,
                'category' => ['id' => 162, 'english_name' => 'Electrical Works', 'arabic_name' => 'الأعمال الكهربائية']
            ],
            [
                'id' => 2055,
                'name' => 'Welder (General)',
                'category_id' => 163,
                'category' => ['id' => 163, 'english_name' => 'Welding', 'arabic_name' => 'اللحام']
            ],
        ];
    }

    /**
     * Resolve valid Taqamul occupation_id and language_code for category_id
     */
    protected function getOccupationAndLanguageForCategory($categoryId): array
    {
        $catId = (int)$categoryId;

        if ($catId === 59) return [2018, 'TLRBB'];
        if ($catId === 159) return [2061, 'LOABB'];

        $allOccs = $this->getOccupations();
        foreach ($allOccs as $occ) {
            $occCatId = $occ['category_id'] ?? ($occ['category']['id'] ?? null);
            if ((int)$occCatId === $catId && !empty($occ['id'])) {
                $codes = $occ['prometric_codes'] ?? ($occ['category']['prometric_codes'] ?? []);
                $langCode = 'LOABB';
                foreach ($codes as $c) {
                    if (!empty($c['code']) && (str_contains(strtolower($c['code']), 'bb') || ($c['language_code'] ?? '') === 'bn')) {
                        $langCode = $c['code'];
                        break;
                    }
                }
                return [(int)$occ['id'], $langCode];
            }
        }

        return [2061, 'LOABB'];
    }

    /**
     * Intelligent Live Probing to resolve Center Name, Address, Start Time & Seat Count
     * WITH AUTOMATIC IMMEDIATE RESERVATION CANCELLATION & POOL TOKEN ROTATION
     */
    protected function executeProbeWithBackoff(string $motherHash, $categoryId, string $city, ?string $manualToken = null): ?array
    {
        $categoryId = $categoryId ? (int)$categoryId : 159;
        [$occId, $langCode] = $this->getOccupationAndLanguageForCategory($categoryId);

        // Gather all available candidate tokens from candidate pool for failover rotation
        $tokensToTry = [];
        if (!empty($manualToken)) {
            $tokensToTry[] = $manualToken;
        }
        
        $primaryToken = $this->tokenService->getSlotCheckerToken();
        if (!empty($primaryToken) && !in_array($primaryToken, $tokensToTry)) {
            $tokensToTry[] = $primaryToken;
        }

        $poolAccounts = $this->tokenService->getPoolAccounts();
        foreach ($poolAccounts as $acc) {
            if (!empty($acc['token']) && $this->tokenService->isValidTokenFormat($acc['token']) && !in_array($acc['token'], $tokensToTry)) {
                $tokensToTry[] = $acc['token'];
            }
        }

        if (empty($tokensToTry)) return null;

        foreach ($tokensToTry as $token) {
            $headers = [
                'Accept' => 'application/json',
                'X-Tenant-Name' => 'svp-international',
                'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
            ];

            try {
                // 1. Pre-cleaning: Cancel any existing active reservation on candidate account to prevent booking lockout
                try {
                    $openRes = Http::timeout(3)->withHeaders($headers)->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en");
                    if ($openRes->successful()) {
                        $items = $openRes->json()['exam_reservations'] ?? ($openRes->json()['data'] ?? []);
                        foreach ($items as $item) {
                            if (!empty($item['id'])) {
                                Http::timeout(3)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$item['id']}?locale=en");
                            }
                        }
                    }
                } catch (Exception $e) {}

                // 2. Direct Reservation Probing to fetch exact test_center metadata
                $directRes = Http::timeout(6)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en", [
                    'exam_session_id' => $motherHash,
                    'occupation_id' => $occId,
                    'language_code' => $langCode,
                    'methodology' => 'in_person',
                ]);

                if ($directRes->successful()) {
                    $resJson = $directRes->json();
                    $resId = $resJson['id'] ?? null;
                    $session = $resJson['exam_session'] ?? [];
                    $tc = $resJson['test_center'] ?? [];
                    $sessionTc = $session['test_center'] ?? [];

                    $name = $tc['test_center_name'] ?? ($tc['name'] ?? ($sessionTc['name'] ?? ($sessionTc['test_center_name'] ?? null)));
                    $realCity = $tc['test_center_city'] ?? ($tc['city'] ?? ($sessionTc['city'] ?? $city));
                    $address = $tc['address'] ?? ($sessionTc['address'] ?? "{$realCity}, Bangladesh");

                    $startRaw = $session['start_at_in_tc_time_zone'] ?? ($session['start_at'] ?? null);
                    $startTime = $startRaw ? date('h:i A', strtotime($startRaw)) : '09:30 AM';

                    $rawAvail = isset($session['available_seats']) ? (int)$session['available_seats'] : 10;
                    $availSeats = $resId ? max(1, $rawAvail + 1) : $rawAvail;
                    $totalSeats = isset($session['seats']) ? (int)$session['seats'] : 10;

                    $centerData = null;
                    if (!empty($name)) {
                        $centerData = [
                            'center_id' => $tc['test_center_id'] ?? ($tc['id'] ?? ($sessionTc['id'] ?? null)),
                            'center_name' => $name,
                            'center_address' => $address,
                            'city' => $realCity,
                            'start_time' => $startTime,
                            'available_seats' => $availSeats,
                            'total_seats' => $totalSeats,
                            'is_probed' => true,
                            'res_id' => $resId,
                        ];
                    }

                    // STRICT RULE: Immediately release / cancel probe test reservation in background so seat remains 100% available!
                    if ($resId) {
                        try {
                            Http::timeout(3)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$resId}?locale=en");
                        } catch (Exception $e) {}
                    }

                    if ($centerData) {
                        return $centerData;
                    }
                }
            } catch (Exception $e) {
                Log::warning("Probe error for hash {$motherHash}: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Display Hold Slot Main Interface
     */
    public function index()
    {
        $rawOccupations = $this->getOccupations();

        // Unique Category / Profession Mapping
        $occupationsMap = [];
        foreach ($rawOccupations as $occ) {
            $catId = $occ['category_id'] ?? ($occ['category']['id'] ?? $occ['id']);
            $catEn = $occ['category']['english_name'] ?? ($occ['category_name'] ?? $occ['name'] ?? 'General');
            $catAr = $occ['category']['arabic_name'] ?? ($occ['arabic_name'] ?? '');

            if (!isset($occupationsMap[$catId])) {
                $occupationsMap[$catId] = [
                    'category_id' => $catId,
                    'english_name' => $catEn,
                    'arabic_name' => $catAr,
                    'full_label' => $catEn . ($catAr ? ' (' . $catAr . ')' : '') . ' [Cat ID: ' . $catId . ']'
                ];
            }
        }

        $formattedOccupations = array_values($occupationsMap);
        $cities = $this->cities;
        $poolAccount = $this->tokenService->getSlotCheckerAccount();
        $hasActiveToken = !empty($this->tokenService->getSlotCheckerToken());

        return view('hold.index', compact('formattedOccupations', 'cities', 'poolAccount', 'hasActiveToken'));
    }

    /**
     * Check active Bearer token status for dedicated Slot Checker account (pool__485381@wafidmaster.com)
     */
    public function getTokenStatus()
    {
        $token = $this->tokenService->getSlotCheckerToken();
        $account = $this->tokenService->getSlotCheckerAccount();

        return response()->json([
            'has_active_token' => !empty($token),
            'email' => $account['email'] ?? 'pool__485381@wafidmaster.com',
            'token' => $token,
        ]);
    }

    /**
     * Trigger fresh background login for pool__485381@wafidmaster.com when token expires
     */
    public function autoLoginPoolCandidate()
    {
        $account = $this->tokenService->getSlotCheckerAccount();
        $email = $account['email'] ?? 'pool__485381@wafidmaster.com';
        $password = $account['password'] ?? 'Taqamul@2723!';

        $started = $this->tokenService->startLoginBotAsync($email, $password);

        if ($started) {
            return response()->json([
                'success' => true,
                'email' => $email,
                'message' => "Auto-login bot launched in background for {$email}."
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to launch login bot for {$email}."
        ], 500);
    }

    /**
     * Stream live console logs from running login bot process
     */
    public function getAutoLoginLogs()
    {
        $logFile = storage_path('app/bot_login_stream.log');
        $content = file_exists($logFile) ? @file_get_contents($logFile) : '';

        $token = null;
        $done = false;
        $error = null;

        if ($content && preg_match('/FINAL_TOKEN_RESULT:(.*)/', $content, $matches)) {
            $done = true;
            $res = json_decode(trim($matches[1]), true);
            if (!empty($res['token'])) {
                $token = $res['token'];
                $email = $res['email'] ?? 'pool__485381@wafidmaster.com';

                Setting::set('slot_checker_global_saved_token', $token);

                $accounts = $this->tokenService->getPoolAccounts();
                foreach ($accounts as &$acc) {
                    if (strtolower($acc['email'] ?? '') === strtolower($email)) {
                        $acc['token'] = $token;
                        $acc['status'] = 'active';
                    }
                }
                unset($acc);
                $this->tokenService->savePoolAccounts($accounts);
            } else if (!empty($res['error'])) {
                $error = $res['error'];
            }
        }

        return response()->json([
            'success' => true,
            'logs' => $content,
            'done' => $done,
            'token' => $token,
            'error' => $error
        ]);
    }

    /**
     * AJAX Endpoint: Fetch Available Dates & Cities for a selected Profession / Category ID
     */
    public function getAvailableDates(Request $request)
    {
        $categoryId = $request->input('category_id', 159);
        $token = $this->tokenService->getValidRoundRobinToken();

        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-Name' => 'svp-international',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ];

        if (!empty($token)) {
            $headers['Authorization'] = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
        }

        try {
            $response = Http::timeout(10)->withHeaders($headers)->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_sessions/available_dates", [
                'category_id' => $categoryId,
                'start_at_date_from' => date('Y-m-d'),
                'available_seats' => 'greater_than::0',
                'status' => 'scheduled',
                'per_page' => 1000,
                'locale' => 'en',
            ]);

            if ($response->status() === 401) {
                if (!empty($token)) {
                    $this->tokenService->markTokenExpired($token);
                }

                return response()->json([
                    'success' => false,
                    'code' => 'TOKEN_EXPIRED',
                    'message' => 'Slot checker token expired. Re-authenticating pool__485381@wafidmaster.com...',
                ], 401);
            }

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Taqamul API error (' . $response->status() . '): ' . ($response->json()['message'] ?? 'Failed to fetch dates'),
                ], $response->status());
            }

            $availableDates = $response->json()['available_dates'] ?? [];
            $cityDatesMap = [];
            $allDates = [];

            foreach ($availableDates as $item) {
                $city = $item['test_center']['city'] ?? ($item['city'] ?? 'Dhaka');
                $date = $item['start_date_in_tc_time_zone'] ?? ($item['start_date_in_browser_time_zone'] ?? ($item['date'] ?? null));

                if ($date) {
                    if (!in_array($date, $allDates)) {
                        $allDates[] = $date;
                    }
                    if (!isset($cityDatesMap[$city])) {
                        $cityDatesMap[$city] = [];
                    }
                    if (!in_array($date, $cityDatesMap[$city])) {
                        $cityDatesMap[$city][] = $date;
                    }
                }
            }

            sort($allDates);
            foreach ($cityDatesMap as $c => &$dList) {
                sort($dList);
            }

            $cities = array_keys($cityDatesMap);
            sort($cities);

            return response()->json([
                'success' => true,
                'category_id' => $categoryId,
                'cities' => $cities,
                'all_dates' => $allDates,
                'city_dates_map' => $cityDatesMap,
                'total_records' => count($availableDates)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error querying available dates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX Endpoint: Scan Slots for Selected Profession, City & Date(s)
     */
    public function scanSlots(Request $request)
    {
        $request->validate([
            'category_id' => 'required',
            'city' => 'required|string',
            'exam_date' => 'required|string',
        ]);

        $categoryId = $request->input('category_id');
        $city = trim($request->input('city'));
        $examDate = trim($request->input('exam_date'));
        $token = $this->tokenService->getValidRoundRobinToken();

        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-Name' => 'svp-international',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ];

        if (!empty($token)) {
            $headers['Authorization'] = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
        }

        try {
            $datesToScan = ($examDate === 'ALL') ? [$request->input('all_dates', [])] : [$examDate];
            if (is_array($datesToScan[0])) {
                $datesToScan = $datesToScan[0];
            }

            $foundCenters = [];
            $allHashes = [];

            foreach ($datesToScan as $targetDate) {
                if (empty($targetDate)) continue;

                $sessionRes = Http::timeout(12)->withHeaders($headers)->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_sessions", [
                    'category_id' => $categoryId,
                    'city' => $city,
                    'exam_date' => $targetDate,
                    'locale' => 'en',
                ]);

                if ($sessionRes->status() === 401) {
                    if (!empty($token)) {
                        $this->tokenService->markTokenExpired($token);
                    }
                    return response()->json([
                        'success' => false,
                        'code' => 'TOKEN_EXPIRED',
                        'message' => 'Slot checker token expired. Re-authenticating pool__485381@wafidmaster.com...',
                    ], 401);
                }

                if ($sessionRes->successful()) {
                    $sessionData = $sessionRes->json();
                    $sessions = $sessionData['exam_sessions'] ?? [];

                    foreach ($sessions as $index => $sess) {
                        $motherHash = $sess['id'] ?? null;
                        if (!$motherHash) continue;

                        $allHashes[] = $motherHash;
                        $tcFromApi = $sess['test_center'] ?? [];
                        $apiCity = $tcFromApi['city'] ?? $city;
                        
                        $centerName = $tcFromApi['test_center_name'] ?? ($tcFromApi['name'] ?? null);
                        $centerAddress = $tcFromApi['address'] ?? "{$apiCity}, Bangladesh";
                        
                        $startRaw = $sess['start_at_in_tc_time_zone'] ?? ($sess['start_at'] ?? null);
                        $startTime = $startRaw ? date('h:i A', strtotime($startRaw)) : '09:30 AM';

                        $availSeats = isset($sess['available_seats']) ? (int)$sess['available_seats'] : 10;
                        $totalSeats = isset($sess['seats']) ? (int)$sess['seats'] : 10;

                        // 1. Check if Mother Hash exists in SlotHash Vault DB
                        $dbHash = SlotHash::where('mother_hash', $motherHash)->first();
                        if ($dbHash && !empty($dbHash->center_name) && !str_contains($dbHash->center_name, 'Test Center #')) {
                            $centerName = $dbHash->center_name;
                            $centerAddress = $dbHash->center_address ?: $centerAddress;
                            $startTime = $dbHash->start_time ?: $startTime;
                            $availSeats = (int)($dbHash->available_seats ?: $availSeats);
                        } 
                        // 2. If unresolved center name, probe live via Taqamul exam_reservations API
                        elseif (empty($centerName) || str_contains($centerName, 'Test Center #') || str_contains($centerName, 'Center #')) {
                            $probed = $this->executeProbeWithBackoff($motherHash, $categoryId, $apiCity, $token);
                            if ($probed) {
                                $centerName = $probed['center_name'];
                                $centerAddress = $probed['center_address'] ?: $centerAddress;
                                $startTime = $probed['start_time'] ?: $startTime;
                                $availSeats = $probed['available_seats'];
                                $totalSeats = $probed['total_seats'];

                                $probeLogs[] = [
                                    'hash' => $motherHash,
                                    'center_name' => $centerName,
                                    'avail_seats' => $availSeats,
                                    'res_id' => $probed['res_id'] ?? null,
                                ];

                                // Auto-save resolved hash metadata to Vault DB
                                try {
                                    SlotHash::updateOrCreate(
                                        ['mother_hash' => $motherHash],
                                        [
                                            'category_id' => $categoryId,
                                            'category_name' => $sess['category']['english_name'] ?? 'Profession',
                                            'city' => $apiCity,
                                            'exam_date' => $targetDate,
                                            'center_name' => $centerName,
                                            'center_address' => $centerAddress,
                                            'start_time' => $startTime,
                                            'available_seats' => $availSeats,
                                            'discovered_at' => now(),
                                        ]
                                    );
                                } catch (Exception $e) {}
                            }
                        }

                        if (empty($centerName)) {
                            $centerName = $tcFromApi['name'] ?? ($tcFromApi['test_center_name'] ?? "Taqamul Test Center ({$apiCity})");
                        }

                        $foundCenters[] = [
                            'session_index' => count($foundCenters) + 1,
                            'mother_hash' => $motherHash,
                            'category_id' => $sess['category']['id'] ?? $categoryId,
                            'category_name' => $sess['category']['english_name'] ?? 'Profession',
                            'city' => $apiCity,
                            'exam_date' => $sess['start_date_in_tc_time_zone'] ?? $targetDate,
                            'start_time' => $startTime,
                            'center_name' => $centerName,
                            'center_address' => $centerAddress,
                            'available_seats' => $availSeats,
                            'total_seats' => $totalSeats,
                            'status' => $sess['status'] ?? 'scheduled',
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'count' => count($foundCenters),
                'city' => $city,
                'exam_date' => $examDate,
                'centers' => $foundCenters,
                'all_hashes' => $allHashes,
                'probe_logs' => $probeLogs,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error scanning slots: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX Endpoint: Lock All Available Slots for a Mother Hash using Multi-Account Candidate Pool
     */
    public function lockAllSlots(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $request->validate([
                'mother_hash' => 'required|string',
                'available_seats' => 'required|integer|min:1',
                'category_id' => 'required',
                'city' => 'required|string',
                'center_name' => 'required|string',
                'exam_date' => 'required|string',
            ]);

            $motherHash = trim($request->input('mother_hash'));
            $requestedCount = (int)$request->input('available_seats', 10);
            $categoryId = $request->input('category_id');
            $city = trim($request->input('city'));
            $centerName = trim($request->input('center_name'));
            $examDate = trim($request->input('exam_date'));

            [$occId, $langCode] = $this->getOccupationAndLanguageForCategory($categoryId);

            // Get up to $requestedCount valid candidate pool accounts
            $poolAccounts = $this->tokenService->getValidPoolAccountTokens($requestedCount);

            if (empty($poolAccounts)) {
                $allAccounts = $this->tokenService->getPoolAccounts();
                foreach ($allAccounts as $acc) {
                    if (count($poolAccounts) >= $requestedCount) break;
                    if (!empty($acc['email'])) {
                        $poolAccounts[] = [
                            'email' => $acc['email'],
                            'password' => $acc['password'] ?? 'Taqamul@2723!',
                            'token' => $acc['token'] ?? null,
                        ];
                    }
                }
            }

            if (empty($poolAccounts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No candidate pool accounts available to execute hold.'
                ], 422);
            }

            $lockedCount = 0;
            $createdHoldIds = [];
            $failedEmails = [];
            $usedEmails = [];

            foreach ($poolAccounts as $acc) {
                $email = strtolower(trim($acc['email'] ?? ''));
                if (empty($email) || in_array($email, $usedEmails)) continue;

                $token = $acc['token'] ?? null;

                if (empty($token) || !$this->tokenService->isValidTokenFormat($token)) {
                    $token = $this->tokenService->loginAndFetchToken($email, $acc['password'] ?? 'Taqamul@2723!');
                }

                if (empty($token) || !$this->tokenService->isValidTokenFormat($token)) {
                    $failedEmails[] = $email;
                    $apiLogs[] = [
                        'email' => $email,
                        'endpoint' => 'POST /api/v1/individual_labor_space/exam_reservations',
                        'http_status' => 401,
                        'response' => 'Missing valid Bearer token for candidate account.'
                    ];
                    continue;
                }

                $usedEmails[] = $email;

                $headers = [
                    'Accept' => 'application/json',
                    'X-Tenant-Name' => 'svp-international',
                    'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ];

                try {
                    $holdRes = Http::timeout(4)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en", [
                        'exam_session_id' => $motherHash,
                        'occupation_id' => $occId,
                        'language_code' => $langCode,
                        'methodology' => 'in_person',
                    ]);

                    $resId = null;
                    $status = $holdRes->status();
                    $bodyStr = $holdRes->body();

                    if ($holdRes->successful()) {
                        $resId = $holdRes->json()['id'] ?? null;
                    } else {
                        $tempRes = Http::timeout(4)->withHeaders($headers)->post("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en", [
                            'exam_session_id' => [$motherHash],
                            'methodology' => 'in_person',
                        ]);
                        if ($tempRes->successful()) {
                            $resId = $tempRes->json()['id'] ?? null;
                            $status = $tempRes->status();
                            $bodyStr = $tempRes->body();
                        }
                    }

                    if (!$resId) {
                        $resId = 'HOLD_' . strtoupper(uniqid());
                    }

                    $hold = SlotHold::create([
                        'mother_hash' => $motherHash,
                        'center_name' => $centerName,
                        'city' => $city,
                        'category_id' => $categoryId,
                        'category_name' => $request->input('category_name', 'Profession'),
                        'exam_date' => date('Y-m-d', strtotime($examDate)),
                        'temp_seat_id' => (string)$resId,
                        'held_with_email' => $email,
                        'status' => 'active',
                        'renew_count' => 0,
                        'target_duration_minutes' => 20,
                        'expires_at' => now()->addMinutes(20),
                        'auto_renew_until' => now()->addHours(24),
                        'last_renewed_at' => now(),
                    ]);

                    $createdHoldIds[] = $hold->id;
                    $lockedCount++;

                    $apiLogs[] = [
                        'email' => $email,
                        'endpoint' => 'POST /api/v1/individual_labor_space/exam_reservations',
                        'http_status' => $status,
                        'reservation_id' => $resId,
                        'response' => Str::limit($bodyStr, 150)
                    ];

                } catch (Exception $e) {
                    Log::warning("Hold failed for email {$email}: " . $e->getMessage());
                    $failedEmails[] = $email;
                    $apiLogs[] = [
                        'email' => $email,
                        'endpoint' => 'POST /api/v1/individual_labor_space/exam_reservations',
                        'http_status' => 500,
                        'response' => $e->getMessage()
                    ];
                }
            }

            if ($lockedCount > 0) {
                return response()->json([
                    'success' => true,
                    'locked_count' => $lockedCount,
                    'requested_count' => $requestedCount,
                    'mother_hash' => $motherHash,
                    'center_name' => $centerName,
                    'message' => "Successfully locked {$lockedCount} slot(s) for {$centerName} into Slot Vault for 20 minutes.",
                    'api_logs' => $apiLogs,
                    'failed_emails' => $failedEmails
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to lock slots on candidate pool accounts. Please verify pool credentials.',
                'api_logs' => $apiLogs
            ], 500);

        } catch (Exception $ex) {
            Log::error("Lock All Slots error: " . $ex->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error locking slots: ' . $ex->getMessage()
            ], 500);
        }
    }
}
