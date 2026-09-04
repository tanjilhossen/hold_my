<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use App\Models\Passenger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Admin Account
        User::updateOrCreate(
            ['email' => 'admin@taqamul.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // Standard User Account
        User::updateOrCreate(
            ['email' => 'user@taqamul.com'],
            [
                'name' => 'Agency Staff',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'is_active' => true,
            ]
        );

        // Default Settings
        Setting::set('temp_mail_provider', 'mail_tm');
        Setting::set('default_country', 'Bangladesh');
        Setting::set('default_country_code', '+880');
        Setting::set('auto_generate_password', '1');
        Setting::set('capsolver_key', 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133');
        Setting::set('capsolver_api_key', 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133');

        // Seed Candidate Pool Accounts
        $jsonPath = database_path('pool_accounts.json');
        if (file_exists($jsonPath)) {
            $poolJson = file_get_contents($jsonPath);
            if (!empty($poolJson)) {
                Setting::set('slot_checker_pool_accounts', $poolJson);
                
                $accounts = json_decode($poolJson, true) ?: [];
                foreach ($accounts as $acc) {
                    if (!empty($acc['email'])) {
                        Passenger::updateOrCreate(
                            ['email' => $acc['email']],
                            [
                                'name' => $acc['name'] ?? 'Candidate',
                                'password' => $acc['password'] ?? 'Taqamul@2723!',
                                'token' => $acc['token'] ?? null,
                                'status' => !empty($acc['token']) ? 'active' : 'expired',
                            ]
                        );
                    }
                }
            }
        }
    }
}
