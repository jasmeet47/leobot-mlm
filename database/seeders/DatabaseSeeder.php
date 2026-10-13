<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Create a new admin safely.
     *
     * Existing accounts are never promoted or modified.
     * Passwords, PINs and balances remain unchanged.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');

        if (empty($adminPassword)) {
            return;
        }

        DB::transaction(function () use ($adminPassword): void {
            $username = env('ADMIN_USERNAME', 'ADMIN');

            // Never modify or promote an existing account.
            if (User::where('username', $username)->exists()) {
                return;
            }

            $admin = new User();

            $admin->fill([
                'username' => $username,
                'sponsor_id' => null,
                'name' => env('ADMIN_NAME', 'Administrator'),
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'phone' => env('ADMIN_PHONE', '0000000000'),
                'password' => Hash::make($adminPassword),
                'security_pin' => null,
                'status' => 'active',
            ]);

            // Role is intentionally not mass assignable.
            // Only this trusted seeder assigns it to a new admin.
            $admin->forceFill([
                'role' => 'admin',
            ]);

            $admin->save();
        });
    }
}