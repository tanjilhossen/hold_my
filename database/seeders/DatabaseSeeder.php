<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
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
        Setting::set('capsolver_key', '');
        Setting::set('twocaptcha_key', '');
    }
}
