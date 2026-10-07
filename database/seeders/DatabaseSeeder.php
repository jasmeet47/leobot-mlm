<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');

        if (empty($adminPassword)) {
            return;
        }

        User::updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'ADMIN')],
            [
                'sponsor_id' => null,
                'name' => env('ADMIN_NAME', 'Administrator'),
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'phone' => env('ADMIN_PHONE', '0000000000'),
                'password' => Hash::make($adminPassword),
                'security_pin' => null,
                'status' => 'active',
            ]
        );
    }
}
