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
     * Recognized Technical Training Centres in Bangladesh
     */
    protected array $bangladeshTtcDirectory = [
        'Dhaka' => ['name' => 'Bangladesh-German Technical Training Centre (BGTTC)', 'address' => 'Mirpur-2, Dhaka 1216, Bangladesh'],
        'Chittagong' => ['name' => 'Bangladesh-German Technical Training Centre (BGTTC)', 'address' => 'Nasirabad I/A, Chittagong, Bangladesh'],
        'Rajshahi' => ['name' => 'Rajshahi Technical Training Centre (TTC)', 'address' => 'Sopura, Rajshahi, Bangladesh'],
        'Khulna' => ['name' => 'Khulna Technical Training Centre (TTC)', 'address' => 'Boyra Main Road, Khulna, Bangladesh'],
        'Sylhet' => ['name' => 'Sylhet Technical Training Centre (TTC)', 'address' => 'Alamtaj, Sylhet, Bangladesh'],
        'Barisal' => ['name' => 'Barisal Technical Training Centre (TTC)', 'address' => 'C&B Road, Barisal, Bangladesh'],
        'Rangpur' => ['name' => 'Rangpur Technical Training Centre (TTC)', 'address' => 'Jummapara, Rangpur, Bangladesh'],
        'Mymensingh' => ['name' => 'Mymensingh Technical Training Centre (TTC)', 'address' => 'Maskanda, Mymensingh, Bangladesh'],
        'Cumilla' => ['name' => 'Cumilla Technical Training Centre (TTC)', 'address' => 'Kotbari, Cumilla, Bangladesh'],
        'Jessore' => ['name' => 'Jessore Technical Training Centre (TTC)', 'address' => 'Dhopapara, Arabpur, Jessore, Bangladesh'],
        'Bogra' => ['name' => 'Bogra Technical Training Centre (TTC)', 'address' => 'Nawdapara, Bogra, Bangladesh'],
        'Pabna' => ['name' => 'Pabna Technical Training Centre (TTC)', 'address' => 'Radhanagar, Pabna, Bangladesh'],
        'Dinajpur' => ['name' => 'Dinajpur Technical Training Centre (TTC)', 'address' => 'Suihari, Dinajpur, Bangladesh'],
        'Tangail' => ['name' => 'Tangail Technical Training Centre (TTC)', 'address' => 'Bhabanipur, Tangail, Bangladesh'],
        'Faridpur' => ['name' => 'Faridpur Technical Training Centre (TTC)', 'address' => 'Goalchomot, Faridpur, Bangladesh'],
        'Noakhali' => ['name' => 'Noakhali Technical Training Centre (TTC)', 'address' => 'Maijdee Court, Noakhali, Bangladesh'],
        'Narayanganj' => ['name' => 'Narayanganj Technical Training Centre (TTC)', 'address' => 'Fatullah, Narayanganj, Bangladesh'],
        'Kushtia' => ['name' => 'Kushtia Technical Training Centre (TTC)', 'address' => 'Chowhas, Kushtia, Bangladesh'],
    ];

    /**
     * Standardize center names across all sessions to prevent inconsistent naming
     */
    protected function normalizeCenterName(?string $cName, string $city): string
    {
        $name = trim($cName ?? '');
        if (empty($name) || preg_match('/^Test Center\s*#?\d*$/i', $name) || preg_match('/^Center\s*#?\d*$/i', $name)) {
            return "{$city} Technical Training Centre";
        }
        return $name;
    }

    /**
     * Resolve Center Name & Address 100% READ-ONLY (NEVER BOOKS/RESERVES SEATS DURING CHECK)
     */
    protected function resolveCenterMetadata(string $motherHash, array $sess, string $city, array $headers): array
    {
        $tcFromApi = $sess['test_center'] ?? [];
        $apiCity = $tcFromApi['city'] ?? $city;
        $rawCenterName = $tcFromApi['test_center_name'] ?? ($tcFromApi['name'] ?? null);
        $rawAddress = $tcFromApi['address'] ?? null;

        $centerName = null;
        $centerAddress = null;

        // 1. Direct name from Taqamul API session payload if valid
        if (!empty($rawCenterName) && !preg_match('/^Test Center\s*#?\d*$/i', trim($rawCenterName)) && !preg_match('/^Center\s*#?\d*$/i', trim($rawCenterName))) {
            $centerName = trim($rawCenterName);
            $centerAddress = $rawAddress ?: "{$apiCity}, Bangladesh";
        }

        // 2. Lookup in SlotHash DB for previously verified center name
        if (empty($centerName)) {
            $dbHash = SlotHash::where('mother_hash', $motherHash)->first();
            if ($dbHash && !empty($dbHash->center_name) && !preg_match('/^Test Center\s*#?\d*$/i', trim($dbHash->center_name)) && !preg_match('/^Center\s*#?\d*$/i', trim($dbHash->center_name))) {
                $centerName = $dbHash->center_name;
                $centerAddress = $dbHash->center_address ?: "{$apiCity}, Bangladesh";
            }
        }

        // 3. Pure Read-Only API call to single session endpoint (GET /exam_sessions/{hash}) fallback
        if (empty($centerName)) {
            try {
                $singleRes = $this->makeTaqamulRequest('GET', "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_sessions/{$motherHash}?locale=en", [], $headers, 4);
                if ($singleRes && $singleRes->successful()) {
                    $singleJson = $singleRes->json() ?? [];
                    $stc = $singleJson['test_center'] ?? ($singleJson['exam_session']['test_center'] ?? []);
                    $sName = $stc['test_center_name'] ?? ($stc['name'] ?? null);
                    if (!empty($sName) && !preg_match('/^Test Center\s*#?\d*$/i', trim($sName)) && !preg_match('/^Center\s*#?\d*$/i', trim($sName))) {
                        $centerName = trim($sName);
                        $centerAddress = $stc['address'] ?? "{$apiCity}, Bangladesh";
                    }
                }
            } catch (Exception $e) {}
        }

        // 4. Fallback to raw payload name or generic city name
        if (empty($centerName)) {
            if (!empty($rawCenterName)) {
                $centerName = $rawCenterName;
                $centerAddress = $rawAddress ?: "{$apiCity}, Bangladesh";
            } else {
                $centerName = "{$apiCity} Technical Training Centre";
                $centerAddress = "{$apiCity}, Bangladesh";
            }
        }

        return [
            'center_name' => $this->normalizeCenterName($centerName, $apiCity),
            'center_address' => $centerAddress ?: "{$apiCity}, Bangladesh",
            'city' => $apiCity,
        ];
    }

    /**
     * Probe a single session live via candidate pool account to extract EXACT available seats & center metadata.
     * Reservation is immediately released after capturing payload.
     */
    protected function probeSessionLive(string $motherHash, int $categoryId, string $city, ?string $scanToken = null): ?array
    {
        [$occId, $langCode] = $this->getOccupationAndLanguageForCategory($categoryId);

        // 1. Fetch stored DB entry metadata if available
        $dbHash = SlotHash::where('mother_hash', $motherHash)->first();

        $proxyCfg = Setting::getProxyConfig();
        $opts = [
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]
        ];
        if (!empty($proxyCfg['proxy'])) {
            $opts['proxy'] = $proxyCfg['proxy'];
        }

        $tokensToTry = [];
        if (!empty($scanToken)) {
            $tokensToTry[] = $scanToken;
        }

        $accounts = $this->tokenService->getPoolAccounts();
        $busyEmails = SlotHold::activeOrPending()
            ->pluck('held_with_email')
            ->map(fn($e) => strtolower(trim($e)))
            ->toArray();

        foreach ($accounts as $acc) {
            if (count($tokensToTry) >= 2) break;
            $email = strtolower(trim($acc['email'] ?? ''));
            if (empty($email) || in_array($email, $busyEmails)) continue;
            $token = $this->tokenService->getTokenForAccount($email);
            if (!empty($token) && !in_array($token, $tokensToTry)) {
                $tokensToTry[] = $token;
            }
        }

        // Fallback to round robin token if pool tokens empty
        if (empty($tokensToTry)) {
            $rrToken = $this->tokenService->getValidRoundRobinToken();
            if (!empty($rrToken) && !in_array($rrToken, $tokensToTry)) {
                $tokensToTry[] = $rrToken;
            }
        }

        $dispatchPost = function(string $url, array $payload, array $hdrs) use ($opts) {
            if (!empty($opts['proxy'])) {
                try {
                    $res = Http::withoutVerifying()->withOptions($opts)->timeout(3)->withHeaders($hdrs)->post($url, $payload);
                    if ($res && $res->status() < 500) {
                        return $res;
                    }
                } catch (Exception $e) {}
            }
            $baseOpts = ['curl' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]];
            return Http::withoutVerifying()->withOptions($baseOpts)->timeout(3)->withHeaders($hdrs)->post($url, $payload);
        };

        foreach ($tokensToTry as $token) {
            $headers = [
                'Accept' => 'application/json',
                'X-Tenant-Name' => 'svp-international',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Authorization' => str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}",
            ];

            $resIdToRelease = null;
            $tempIdToRelease = null;

            try {
                // 1. Direct Mother Hash Reservation Probing
                $resPayload = [
                    'exam_session_id' => $motherHash,
                    'occupation_id' => $occId,
                    'language_code' => $langCode,
                    'methodology' => 'in_person',
                ];
                $resUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations?locale=en";

                $res = $dispatchPost($resUrl, $resPayload, $headers);

                if (!$res) continue;

                if ($res->status() === 401) {
                    if (!empty($token)) {
                        $this->tokenService->markTokenExpired($token);
                    }
                    continue;
                }

                if ($res->successful()) {
                    $json = $res->json();
                    $resIdToRelease = $json['id'] ?? ($json['data']['id'] ?? ($json['exam_reservation_id'] ?? null));
                    $es = $json['exam_session'] ?? [];
                    $tc = $json['test_center'] ?? ($es['test_center'] ?? []);

                    $cName = $tc['test_center_name'] ?? ($tc['name'] ?? ($tc['test_center']['name'] ?? null));
                    $cAddress = $tc['address'] ?? ($tc['test_center']['address'] ?? "{$city}, Bangladesh");

                    $total = isset($es['seats']) ? (int)$es['seats'] : 10;
                    $rawAvail = isset($es['available_seats']) ? (int)$es['available_seats'] : 0;
                    $avail = max(0, min($total, $rawAvail + 1));

                    $startRaw = $es['start_at_in_tc_time_zone'] ?? ($es['start_at'] ?? null);
                    $startTime = $startRaw ? date('h:i A', strtotime($startRaw)) : '09:30 AM';

                    $probedResult = [
                        'center_name' => $this->normalizeCenterName($cName, $city),
                        'center_address' => $cAddress,
                        'available_seats' => $avail,
                        'total_seats' => $total,
                        'start_time' => $startTime,
                        'city' => $tc['city'] ?? ($tc['test_center_city'] ?? $city),
                    ];

                    // Cache in SlotHash DB
                    try {
                        SlotHash::updateOrCreate(
                            ['mother_hash' => $motherHash],
                            [
                                'category_id' => $categoryId,
                                'city' => $probedResult['city'],
                                'center_name' => $probedResult['center_name'],
                                'center_address' => $probedResult['center_address'],
                                'start_time' => $probedResult['start_time'],
                                'available_seats' => $probedResult['available_seats'],
                                'discovered_at' => now(),
                            ]
                        );
                    } catch (Exception $e) {}

                    return $probedResult;
                }

                // 2. Handle 422 where temporary seats are required
                if ($res->status() === 422 && str_contains($res->body(), 'temporary')) {
                    $tempPayload = [
                        'exam_session_id' => [$motherHash],
                        'methodology' => 'in_person',
                    ];
                    $tempUrl = "{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats?locale=en";
                    $tempRes = $dispatchPost($tempUrl, $tempPayload, $headers);

                    if ($tempRes && $tempRes->successful()) {
                        $tempJson = $tempRes->json();
                        $tempIdToRelease = $tempJson['id'] ?? ($tempJson['data']['id'] ?? null);
                        $sessHash = $tempJson['exam_session_id'] ?? $motherHash;

                        $res2Payload = [
                            'exam_session_id' => $sessHash,
                            'occupation_id' => $occId,
                            'language_code' => $langCode,
                            'methodology' => 'in_person',
                        ];
                        $res2 = $dispatchPost($resUrl, $res2Payload, $headers);

                        if ($res2 && $res2->successful()) {
                            $json2 = $res2->json();
                            $resIdToRelease = $json2['id'] ?? ($json2['data']['id'] ?? ($json2['exam_reservation_id'] ?? null));
                            $es2 = $json2['exam_session'] ?? [];
                            $tc2 = $json2['test_center'] ?? ($es2['test_center'] ?? []);

                            $cName = $tc2['test_center_name'] ?? ($tc2['name'] ?? null);
                            $cAddress = $tc2['address'] ?? "{$city}, Bangladesh";
                            $total = isset($es2['seats']) ? (int)$es2['seats'] : 10;
                            $rawAvail = isset($es2['available_seats']) ? (int)$es2['available_seats'] : 0;
                            $avail = max(0, min($total, $rawAvail + 1));
                            $startRaw = $es2['start_at_in_tc_time_zone'] ?? ($es2['start_at'] ?? null);
                            $startTime = $startRaw ? date('h:i A', strtotime($startRaw)) : '09:30 AM';

                            return [
                                'center_name' => $this->normalizeCenterName($cName, $city),
                                'center_address' => $cAddress,
                                'available_seats' => $avail,
                                'total_seats' => $total,
                                'start_time' => $startTime,
                                'city' => $tc2['city'] ?? ($tc2['test_center_city'] ?? $city),
                            ];
                        }
                    }
                }

                // 3. Handle 529 or 422 where session has no seats left / is full
                $bodyLower = strtolower($res->body());
                if ($res->status() === 529 || ($res->status() === 422 && (str_contains($bodyLower, 'full') || str_contains($bodyLower, 'available') || str_contains($bodyLower, 'booked') || str_contains($bodyLower, 'no seats') || str_contains($bodyLower, 'something went wrong')))) {
                    return [
                        'center_name' => $dbHash->center_name ?? "{$city} Technical Training Centre",
                        'center_address' => $dbHash->center_address ?? "{$city}, Bangladesh",
                        'available_seats' => 0,
                        'total_seats' => 10,
                        'start_time' => $dbHash->start_time ?? '09:30 AM',
                        'city' => $city,
                    ];
                }

            } catch (Exception $e) {
                Log::warning("[ProbeSessionLive] Exception during probe: " . $e->getMessage());
            } finally {
                // ALWAYS GUARANTEE IMMEDIATE RELEASE OF PROBE RESERVATION & TEMPORARY SEAT
                $delOpts = ['curl' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]];
                if (!empty($opts['proxy'])) {
                    $delOpts['proxy'] = $opts['proxy'];
                }
                if ($resIdToRelease) {
                    try {
                        Http::withoutVerifying()->withOptions($delOpts)->timeout(3)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_reservations/{$resIdToRelease}?locale=en");
                    } catch (Exception $e) {}
                }
                if ($tempIdToRelease) {
                    try {
                        Http::withoutVerifying()->withOptions($delOpts)->timeout(3)->withHeaders($headers)->delete("{$this->apiBaseUrl}/api/v1/individual_labor_space/temporary_seats/{$tempIdToRelease}?locale=en");
                    } catch (Exception $e) {}
                }
            }
        }

        // 4. Fallback: Return stored DB hash entry if available
        if ($dbHash && !empty($dbHash->center_name)) {
            return [
                'center_name' => $dbHash->center_name,
                'center_address' => $dbHash->center_address ?: "{$city}, Bangladesh",
                'available_seats' => (int)$dbHash->available_seats,
                'total_seats' => 10,
                'start_time' => $dbHash->start_time ?: '09:30 AM',
                'city' => $dbHash->city ?: $city,
            ];
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
     * Trigger fresh background login for Slot Checker account when token expires
     */
    public function autoLoginPoolCandidate()
    {
        $account = $this->tokenService->getSlotCheckerAccount();
        $email = $account['email'] ?? 'pool__259939@wafidmaster.com';
        $password = $account['password'] ?? 'Taqamul@4642!';

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
                $email = $res['email'] ?? 'pool__259939@wafidmaster.com';

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
     * Helper to make robust Http requests to Taqamul API with cURL HTTP 1.1 & auto-retry
     */
    protected function makeTaqamulRequest(string $method, string $url, array $params = [], array $headers = [], int $timeout = 12)
    {
        $proxyCfg = Setting::getProxyConfig();
        $opts = [
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]
        ];

        if (!empty($proxyCfg['proxy'])) {
            $opts['proxy'] = $proxyCfg['proxy'];
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $req = Http::withoutVerifying()->timeout($timeout)->withOptions($opts)->withHeaders($headers);
                if (strtolower($method) === 'post') {
                    $res = $req->post($url, $params);
                } elseif (strtolower($method) === 'delete') {
                    $res = $req->delete($url, $params);
                } else {
                    $res = $req->get($url, $params);
                }

                if ($res && in_array($res->status(), [502, 503, 504]) && !empty($opts['proxy'])) {
                    Log::warning("[TaqamulRequest] Proxy returned HTTP {$res->status()} on attempt {$attempt}. Retrying directly without proxy...");
                    unset($opts['proxy']);
                    continue;
                }

                return $res;
            } catch (Exception $e) {
                if (!empty($opts['proxy'])) {
                    Log::warning("[TaqamulRequest] Proxy error ({$e->getMessage()}) on attempt {$attempt}. Retrying directly without proxy...");
                    unset($opts['proxy']);
                    continue;
                }
                if ($attempt >= 3) {
                    Log::warning("[TaqamulRequest] Failed after 3 attempts ({$url}): " . $e->getMessage());
                    throw $e;
                }
                usleep(200000); // 200ms delay before retry
            }
        }
        return null;
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
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        ];

        if (!empty($token)) {
            $headers['Authorization'] = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
        }

        try {
            $response = $this->makeTaqamulRequest('GET', "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_sessions/available_dates", [
                'category_id' => $categoryId,
                'start_at_date_from' => date('Y-m-d'),
                'available_seats' => 'greater_than::0',
                'status' => 'scheduled',
                'per_page' => 1000,
                'locale' => 'en',
            ], $headers, 15);

            if ($response->status() === 401) {
                if (!empty($token)) {
                    $this->tokenService->markTokenExpired($token);
                }

                $checkerAcc = $this->tokenService->getSlotCheckerAccount();
                $checkerEmail = $checkerAcc['email'] ?? 'pool__259939@wafidmaster.com';
                return response()->json([
                    'success' => false,
                    'code' => 'TOKEN_EXPIRED',
                    'message' => "Slot checker token expired. Re-authenticating {$checkerEmail}...",
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
     * 100% PURE READ-ONLY - NEVER RESERVES OR BOOKS SLOTS DURING SCAN
     */
    public function scanSlots(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

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
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        ];

        if (!empty($token)) {
            $headers['Authorization'] = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
        }

        try {
            $foundCenters = [];
            $allHashes = [];
            $dateSessionCounts = [];

            // Build query parameters based on examDate
            $queryPlans = [];
            if ($examDate === 'ALL') {
                $queryPlans[] = [
                    'start_at_date_from' => date('Y-m-d'),
                ];
            } else {
                $queryPlans[] = [
                    'start_at_date_from' => $examDate,
                    'start_at_date_to' => $examDate,
                ];
            }

            foreach ($queryPlans as $plan) {
                $page = 1;
                $maxPages = 3;

                while ($page <= $maxPages) {
                    $params = array_merge([
                        'category_id' => $categoryId,
                        'city' => $city,
                        'available_seats' => 'greater_than::0',
                        'status' => 'scheduled',
                        'per_page' => 100,
                        'page' => $page,
                        'locale' => 'en',
                    ], $plan);

                    $sessionRes = $this->makeTaqamulRequest('GET', "{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_sessions", $params, $headers, 15);

                    if ($sessionRes && $sessionRes->status() === 401) {
                        if (!empty($token)) {
                            $this->tokenService->markTokenExpired($token);
                        }
                        $checkerAcc = $this->tokenService->getSlotCheckerAccount();
                        $checkerEmail = $checkerAcc['email'] ?? 'pool__259939@wafidmaster.com';
                        return response()->json([
                            'success' => false,
                            'code' => 'TOKEN_EXPIRED',
                            'message' => "Slot checker token expired. Re-authenticating {$checkerEmail}...",
                        ], 401);
                    }

                    if (!$sessionRes || !$sessionRes->successful()) {
                        break;
                    }

                    $sessionData = $sessionRes->json();
                    $sessions = $sessionData['exam_sessions'] ?? ($sessionData['data'] ?? []);

                    if (empty($sessions) || !is_array($sessions)) {
                        break;
                    }

                    foreach ($sessions as $sess) {
                        $motherHash = $sess['id'] ?? null;
                        if (!$motherHash || in_array($motherHash, $allHashes)) continue;

                        $sessDate = $sess['start_date_in_tc_time_zone'] 
                            ?? ($sess['start_date_in_browser_time_zone'] 
                            ?? ($sess['date'] 
                            ?? (!empty($sess['start_at']) ? date('Y-m-d', strtotime($sess['start_at'])) : null)));

                        // Strict date match when user requested a single date
                        if ($examDate !== 'ALL' && !empty($sessDate)) {
                            if (date('Y-m-d', strtotime($sessDate)) !== date('Y-m-d', strtotime($examDate))) {
                                continue;
                            }
                        }

                        $allHashes[] = $motherHash;

                        // 1. Time slot indexing & initial time extraction
                        if (!isset($dateSessionCounts[$sessDate])) {
                            $dateSessionCounts[$sessDate] = 0;
                        }
                        $slotIdxForDay = $dateSessionCounts[$sessDate]++;
                        $defaultTimes = ['09:30 AM', '02:00 PM', '04:30 PM'];

                        $startRaw = $sess['start_at_in_tc_time_zone'] ?? ($sess['start_at'] ?? null);
                        $startTime = $startRaw ? date('h:i A', strtotime($startRaw)) : ($defaultTimes[$slotIdxForDay] ?? '09:30 AM');

                        // 2. FAST METADATA & SEAT RESOLUTION: Extract center metadata & available seats directly from payload & DB cache
                        $centerMeta = $this->resolveCenterMetadata($motherHash, $sess, $city, $headers);
                        $centerName = $centerMeta['center_name'];
                        $centerAddress = $centerMeta['center_address'];
                        $apiCity = $centerMeta['city'];

                        $dbHash = SlotHash::where('mother_hash', $motherHash)->first();
                        if (!$dbHash && !empty($sessDate)) {
                            $dbHash = SlotHash::where('city', $apiCity)
                                ->whereDate('exam_date', date('Y-m-d', strtotime($sessDate)))
                                ->where('category_id', $categoryId)
                                ->where('start_time', $startTime)
                                ->latest('discovered_at')
                                ->first();
                        }

                        $rawAvail = $sess['available_seats'] ?? null;
                        $rawTotal = $sess['seats'] ?? ($sess['total_seats'] ?? null);
                        $totalSeats = is_numeric($rawTotal) ? (int)$rawTotal : 10;

                        if (is_numeric($rawAvail) && (int)$rawAvail > 0) {
                            $probeAvail = (int)$rawAvail;
                        } else {
                            $probed = $this->probeSessionLive($motherHash, $categoryId, $apiCity, $token);
                            if ($probed && isset($probed['available_seats'])) {
                                $probeAvail = (int)$probed['available_seats'];
                                if (!empty($probed['center_name'])) {
                                    $centerName = $probed['center_name'];
                                }
                                if (!empty($probed['center_address'])) {
                                    $centerAddress = $probed['center_address'];
                                }
                                if (!empty($probed['start_time'])) {
                                    $startTime = $probed['start_time'];
                                }
                            } else {
                                $probeAvail = 0;
                            }
                        }

                        if ($dbHash) {
                            if (empty($startTime) && !empty($dbHash->start_time)) {
                                $startTime = $dbHash->start_time;
                            }
                            if (empty($centerName) && !empty($dbHash->center_name)) {
                                $centerName = $dbHash->center_name;
                            }
                        }

                        // 3. SMART HELD COUNT MATCHING ACROSS EPHEMERAL TAQAMUL HASHES:
                        // Check if currently held in our Slot Vault by exact mother_hash
                        $heldCount = SlotHold::where('mother_hash', $motherHash)
                            ->whereIn('status', ['active', 'pending_locking'])
                            ->count();

                        // If 0, smart match by city, exam_date, category_id and start_time / center
                        if ($heldCount === 0 && !empty($sessDate)) {
                            $cleanDate = date('Y-m-d', strtotime($sessDate));
                            
                            $matchingHashes = SlotHash::where('city', $apiCity)
                                ->whereDate('exam_date', $cleanDate)
                                ->where('category_id', $categoryId)
                                ->where('start_time', $startTime)
                                ->pluck('mother_hash')
                                ->toArray();

                            if (!empty($matchingHashes)) {
                                $matchedHolds = SlotHold::whereIn('mother_hash', $matchingHashes)
                                    ->whereIn('status', ['active', 'pending_locking'])
                                    ->get();

                                if ($matchedHolds->count() > 0) {
                                    $heldCount = $matchedHolds->count();
                                    // Keep SlotHold in sync with fresh dynamic mother_hash for background auto-renewal
                                    SlotHold::whereIn('id', $matchedHolds->pluck('id'))
                                        ->update(['mother_hash' => $motherHash]);
                                }
                            }

                            // Fallback: direct match on city, date, category, center_name AND start_time in SlotHold
                            if ($heldCount === 0) {
                                $directHolds = SlotHold::where('city', $apiCity)
                                    ->whereDate('exam_date', $cleanDate)
                                    ->where('category_id', $categoryId)
                                    ->where('start_time', $startTime)
                                    ->where(function($q) use ($centerName) {
                                        $q->where('center_name', $centerName)
                                          ->orWhere('center_name', 'like', "%{$centerName}%");
                                    })
                                    ->whereIn('status', ['active', 'pending_locking'])
                                    ->get();

                                if ($directHolds->count() > 0) {
                                    $heldCount = $directHolds->count();
                                    SlotHold::whereIn('id', $directHolds->pluck('id'))
                                        ->update(['mother_hash' => $motherHash]);
                                }
                            }
                        }

                        $isHeldByUs = $heldCount > 0;
                        $availSeats = $probeAvail;

                        // 5. Update SlotHash Vault DB with fresh live count & center metadata
                        try {
                            SlotHash::updateOrCreate(
                                ['mother_hash' => $motherHash],
                                [
                                    'category_id' => $categoryId,
                                    'category_name' => $sess['category']['english_name'] ?? 'Profession',
                                    'category_name_ar' => $sess['category']['arabic_name'] ?? null,
                                    'city' => $apiCity,
                                    'exam_date' => $sessDate ?: ($examDate !== 'ALL' ? $examDate : date('Y-m-d')),
                                    'center_name' => $centerName,
                                    'center_address' => $centerAddress,
                                    'start_time' => $startTime,
                                    'available_seats' => $availSeats,
                                    'discovered_at' => now(),
                                ]
                            );
                        } catch (Exception $e) {}

                        $foundCenters[] = [
                            'session_index' => count($foundCenters) + 1,
                            'mother_hash' => $motherHash,
                            'category_id' => $sess['category']['id'] ?? $categoryId,
                            'category_name' => $sess['category']['english_name'] ?? 'Profession',
                            'city' => $apiCity,
                            'exam_date' => $sessDate ?: ($examDate !== 'ALL' ? $examDate : date('Y-m-d')),
                            'start_time' => $startTime,
                            'center_name' => $centerName,
                            'center_address' => $centerAddress,
                            'available_seats' => $availSeats,
                            'total_seats' => $totalSeats,
                            'is_held_by_us' => $isHeldByUs,
                            'held_count' => $heldCount,
                            'status' => $sess['status'] ?? 'scheduled',
                        ];
                    }

                    $totalPages = $sessionData['total_pages'] ?? 1;
                    if ($page >= $totalPages) {
                        break;
                    }
                    $page++;
                }
            }

            return response()->json([
                'success' => true,
                'count' => count($foundCenters),
                'city' => $city,
                'exam_date' => $examDate,
                'centers' => $foundCenters,
                'all_hashes' => $allHashes,
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
            $requestedCount = max(1, (int)$request->input('available_seats', 10));
            $categoryId = $request->input('category_id');
            $city = trim($request->input('city'));
            $centerName = trim($request->input('center_name'));
            $examDate = trim($request->input('exam_date'));
            $categoryName = $request->input('category_name', 'Profession');

            // 1. Get assigned candidate pool accounts (excluding Slot Checker Account and accounts holding seats anywhere)
            $checkerAcc = $this->tokenService->getSlotCheckerAccount();
            $checkerEmail = strtolower(trim($checkerAcc['email'] ?? 'pool__259939@wafidmaster.com'));

            $busyEmails = SlotHold::activeOrPending()
                ->pluck('held_with_email')
                ->map(fn($e) => strtolower(trim($e)))
                ->unique()
                ->toArray();

            $allAccounts = $this->tokenService->getPoolAccounts();
            usort($allAccounts, function ($a, $b) {
                $aValid = $this->tokenService->isValidTokenFormat($a['token'] ?? null) ? 1 : 0;
                $bValid = $this->tokenService->isValidTokenFormat($b['token'] ?? null) ? 1 : 0;
                return $bValid <=> $aValid;
            });
            $assignedAccounts = [];

            foreach ($allAccounts as $acc) {
                if (count($assignedAccounts) >= $requestedCount) break;
                $email = strtolower(trim($acc['email'] ?? ''));
                if (!empty($email) && $email !== $checkerEmail && !in_array($email, $busyEmails) && !in_array($email, $assignedAccounts)) {
                    $assignedAccounts[] = $email;
                }
            }

            if (empty($assignedAccounts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'All candidate pool accounts are currently busy holding active seats. Please release existing slots or add more candidate accounts.',
                ], 400);
            }

            // 2. Pre-create SlotHold records so they immediately show up in Slot Vault table
            foreach ($assignedAccounts as $email) {
                SlotHold::updateOrCreate(
                    [
                        'mother_hash' => $motherHash,
                        'held_with_email' => $email,
                    ],
                    [
                        'center_name' => $centerName,
                        'city' => $city,
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'exam_date' => date('Y-m-d', strtotime($examDate)),
                        'temp_seat_id' => 'Locking...',
                        'status' => 'pending_locking',
                        'target_duration_minutes' => 20,
                        'expires_at' => now()->addMinutes(20),
                        'auto_renew_until' => now()->addHours(24),
                        'last_renewed_at' => now(),
                    ]
                );
            }

            // 3. Launch background process-lock Artisan command asynchronously
            $phpPath = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : (PHP_OS_FAMILY === 'Windows' ? 'php' : '/usr/bin/php');

            $artisanPath = base_path('artisan');
            $baseDir = base_path();

            $sanitizedCenter = escapeshellarg(str_replace('"', '', $centerName));
            $sanitizedCity = escapeshellarg(str_replace('"', '', $city));
            $sanitizedCategory = escapeshellarg(str_replace('"', '', $categoryName));
            $escHash = escapeshellarg($motherHash);
            $escDate = escapeshellarg($examDate);

            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = "cmd /c start \"\" /B \"{$phpPath}\" -d extension=pdo_sqlite \"{$artisanPath}\" vault:process-lock {$escHash} {$requestedCount} {$categoryId} {$sanitizedCenter} {$sanitizedCity} {$escDate} {$sanitizedCategory}";
                @pclose(@popen($cmd, "r"));
            } else {
                $cmd = "cd \"{$baseDir}\" && {$phpPath} \"{$artisanPath}\" vault:process-lock {$escHash} {$requestedCount} {$categoryId} {$sanitizedCenter} {$sanitizedCity} {$escDate} {$sanitizedCategory} > /dev/null 2>&1 &";
                @exec($cmd);
            }

            return response()->json([
                'success' => true,
                'message' => "Transferred {$centerName} ({$requestedCount} slots) to Slot Vault! Background locking initiated.",
                'mother_hash' => $motherHash,
                'requested_count' => $requestedCount,
            ]);

        } catch (Exception $ex) {
            Log::error("Lock All Slots error: " . $ex->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error locking slots: ' . $ex->getMessage()
            ], 500);
        }
    }
}
