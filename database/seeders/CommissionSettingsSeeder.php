<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommissionSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            1  => 30,
            2  => 5,
            3  => 4,
            4  => 3,
            5  => 2,
            6  => 1,
            7  => 5,
            8  => 4,
            9  => 3,
            10 => 2,
            11 => 1,
            12 => 5,
            13 => 4,
            14 => 3,
            15 => 2,
            16 => 1,
            17 => 5,
            18 => 4,
            19 => 3,
            20 => 2,
            21 => 1,
        ];

        for ($level = 22; $level <= 51; $level++) {
            $rates[$level] = 0.333333;
        }

        foreach ($rates as $level => $percentage) {
            DB::table('commission_settings')->insertOrIgnore([
                'level' => $level,
                'commission_percent' => $percentage,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}