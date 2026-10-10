<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * LeoBot MLM - Safe Admin Seeder
     *
     * Rules:
     *
     * 1. Create admin only if username does not exist.
     * 2. Never reset existing admin password.
     * 3. Never reset existing admin security PIN.
     * 4. Never overwrite existing admin details.
     * 5. Never change wallet balances.
     * 6. Never modify commission settings.
     * 7. Safe for repeated Render deployments.
     */

    public function run(): void
    {
        /*
         * Read initial admin password
         * from environment configuration.
         */

        $adminPassword = env('ADMIN_PASSWORD');

        /*
         * Without ADMIN_PASSWORD,
         * do not create or modify any account.
         */

        if (empty($adminPassword)) {
            return;
        }

        /*
         * Create admin only if it does not exist.
         *
         * firstOrCreate prevents existing
         * admin details from being overwritten.
         */

        User::firstOrCreate(
            [
                'username' => env(
                    'ADMIN_USERNAME',
                    'ADMIN'
                ),
            ],
            [
                'sponsor_id' => null,

                'name' => env(
                    'ADMIN_NAME',
                    'Administrator'
                ),

                'email' => env(
                    'ADMIN_EMAIL',
                    'admin@example.com'
                ),

                'phone' => env(
                    'ADMIN_PHONE',
                    '0000000000'
                ),

                'password' => Hash::make(
                    $adminPassword
                ),

                'security_pin' => null,

                'status' => 'active',
            ]
        );
    }
}