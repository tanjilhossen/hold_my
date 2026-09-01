<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Services\TaqamulTokenService;

class ImportTaqamulMetadata extends Command
{
    protected $signature = 'taqamul:import-metadata';
    protected $description = 'Import all Professions, Categories, and Cities from Taqamul live API';

    protected string $apiBaseUrl = 'https://svp-international-api.pacc.sa';

    public function handle(TaqamulTokenService $tokenService)
    {
        $this->info('Starting Taqamul Metadata Import...');

        $token = $tokenService->getValidRoundRobinToken();
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-Name' => 'svp-international',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        ];
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        // 1. Fetch All Occupations
        $this->info('Fetching all Occupations...');
        $occupations = [];
        try {
            $occRes = Http::timeout(20)->withHeaders($headers)->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/occupations", [
                'locale' => 'en',
                'per_page' => 500,
            ]);
            if ($occRes->successful()) {
                $occData = $occRes->json();
                $occupations = $occData['occupations'] ?? $occData;
                $this->info('Successfully fetched ' . count($occupations) . ' occupations.');
            }
        } catch (\Exception $e) {
            $this->error('Failed to fetch occupations: ' . $e->getMessage());
        }

        // 2. Fetch All Categories
        $this->info('Fetching all Categories...');
        $categories = [];
        try {
            $catRes = Http::timeout(20)->withHeaders($headers)->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/categories", [
                'locale' => 'en',
                'per_page' => 500,
            ]);
            if ($catRes->successful()) {
                $catData = $catRes->json();
                $categories = $catData['categories'] ?? $catData;
                $this->info('Successfully fetched ' . count($categories) . ' categories.');
            }
        } catch (\Exception $e) {
            $this->error('Failed to fetch categories: ' . $e->getMessage());
        }

        // 3. Extract All Cities Across Bangladesh Test Centers & Available Dates
        $this->info('Discovering all Cities in Bangladesh...');
        $citiesSet = [
            'Khulna',
            'Dhaka',
            'Chittagong',
            'Rajshahi',
            'Sylhet',
            'Barisal',
            'Rangpur',
            'Mymensingh',
            'Cumilla',
            'Jessore',
            'Kushtia',
            'Nilphamari',
            'Bogra',
            'Dinajpur',
            'Tangail',
            'Faridpur',
            'Noakhali',
            'Narayanganj',
            'Gazipur',
            'Cox\'s Bazar',
            'Pabna',
            'Brahmanbaria',
            'Feni',
            'Manikganj',
            'Narsingdi',
            'Kishoreganj',
            'Jamalpur',
            'Sherpur',
            'Sirajganj',
            'Natore',
            'Naogaon',
            'Chapainawabganj',
            'Kurigram',
            'Gaibandha',
            'Lalmonirhat',
            'Panchagarh',
            'Thakurgaon',
            'Satkhira',
            'Bagerhat',
            'Jhenaidah',
            'Magura',
            'Narail',
            'Meherpur',
            'Chuadanga',
            'Patuakhali',
            'Bhola',
            'Pirojpur',
            'Jhalokathi',
            'Barguna',
            'Habiganj',
            'Moulvibazar',
            'Sunamganj',
            'Chandpur',
            'Lakshmipur',
            'Gopalganj',
            'Madaripur',
            'Shariatpur',
            'Rajbari',
            'Netrokona',
            'Bandarban',
            'Khagrachhari',
            'Rangamati'
        ];

        // Sample dates from various categories to extract active cities
        $sampleCategories = [159, 37, 45, 48, 59, 10, 20, 30, 40, 50];
        foreach ($sampleCategories as $cId) {
            try {
                $dRes = Http::timeout(8)->withHeaders($headers)->get("{$this->apiBaseUrl}/api/v1/individual_labor_space/exam_sessions/available_dates", [
                    'category_id' => $cId,
                    'start_at_date_from' => date('Y-m-d'),
                    'per_page' => 500,
                    'status' => 'scheduled',
                    'locale' => 'en',
                ]);
                if ($dRes->successful()) {
                    $dates = $dRes->json()['available_dates'] ?? [];
                    foreach ($dates as $d) {
                        if (!empty($d['test_center']['city'])) {
                            $city = trim($d['test_center']['city']);
                            if (!in_array($city, $citiesSet)) {
                                $citiesSet[] = $city;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        // Sort alphabetically
        sort($citiesSet);

        // 4. Save to Persistent JSON Storage
        Storage::disk('local')->put('taqamul_occupations.json', json_encode($occupations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Storage::disk('local')->put('taqamul_categories.json', json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Storage::disk('local')->put('taqamul_cities.json', json_encode($citiesSet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 5. Update Cache
        Cache::forever('taqamul_occupations_list', $occupations);
        Cache::forever('taqamul_categories_list', $categories);
        Cache::forever('taqamul_cities_list', $citiesSet);

        $this->info('✅ Metadata Import Completed:');
        $this->line('- Total Occupations: ' . count($occupations));
        $this->line('- Total Categories: ' . count($categories));
        $this->line('- Total Cities: ' . count($citiesSet));

        return Command::SUCCESS;
    }
}
