<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * LeoBot MLM
     *
     * Update 51-Level Commission Rates
     *
     * Applies to:
     *
     * 1. Investment Distribution
     * 2. Trading Profit Generation Sharing
     *
     * Both systems use separate funding pools.
     *
     * This migration does NOT:
     * - Change member principal
     * - Change wallet balances
     * - Create income payouts
     * - Enable any distribution system
     */

    public function up(): void
    {
        /*
         * NEW 51-LEVEL COMMISSION RATES
         */

        $rates = [

            // LEVEL 1
            1 => '30.000000000',

            // LEVEL 2 TO 6
            2 => '5.000000000',
            3 => '4.000000000',
            4 => '3.000000000',
            5 => '2.000000000',
            6 => '1.000000000',

            // LEVEL 7 TO 11
            7 => '5.000000000',
            8 => '4.000000000',
            9 => '3.000000000',
            10 => '2.000000000',
            11 => '1.000000000',

            // LEVEL 12 TO 16
            12 => '5.000000000',
            13 => '4.000000000',
            14 => '3.000000000',
            15 => '2.000000000',
            16 => '1.000000000',

            // LEVEL 17 TO 21
            17 => '2.500000000',
            18 => '4.000000000',
            19 => '3.000000000',
            20 => '2.000000000',
            21 => '1.000000000',
        ];

        /*
         * LEVEL 22 TO 41
         *
         * Each level receives 0.5%
         */

        for ($level = 22; $level <= 41; $level++) {
            $rates[$level] = '0.500000000';
        }

        /*
         * LEVEL 42 TO 51
         *
         * Each level receives 0.25%
         */

        for ($level = 42; $level <= 51; $level++) {
            $rates[$level] = '0.250000000';
        }

        ksort($rates);

        /*
         * VERIFY ALL 51 LEVELS
         */

        if (array_keys($rates) !== range(1, 51)) {
            throw new RuntimeException(
                'Exactly 51 commission levels are required.'
            );
        }

        /*
         * VERIFY TOTAL COMMISSION = 100%
         */

        $total = '0.000000000';

        foreach ($rates as $rate) {
            $total = bcadd(
                $total,
                $rate,
                9
            );
        }

        if (
            bccomp(
                $total,
                '100.000000000',
                9
            ) !== 0
        ) {
            throw new RuntimeException(
                'Total commission must equal exactly 100%.'
            );
        }

        /*
         * ATOMIC DATABASE TRANSACTION
         *
         * Both tables must update together.
         */

        DB::transaction(function () use ($rates) {

            /*
             * CHECK INVESTMENT DISTRIBUTION SETTINGS
             */

            $investment = DB::table(
                'investment_distribution_settings'
            )
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$investment) {
                throw new RuntimeException(
                    'Investment distribution settings missing.'
                );
            }

            /*
             * Investment Distribution must be OFF.
             */

            if ((bool) $investment->is_enabled) {
                throw new RuntimeException(
                    'Disable investment distribution before updating rates.'
                );
            }

            /*
             * CHECK TRADING PROFIT GENERATION SETTINGS
             */

            $generation = DB::table(
                'generation_pool_settings'
            )
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$generation) {
                throw new RuntimeException(
                    'Generation pool settings missing.'
                );
            }

            /*
             * Trading Profit Generation must be OFF.
             *
             * Correct database column:
             * generation_enabled
             */

            if ((bool) $generation->generation_enabled) {
                throw new RuntimeException(
                    'Disable trading profit generation before updating rates.'
                );
            }

            /*
             * BOTH COMMISSION TABLES
             */

            $tables = [
                'investment_distribution_level_rates',
                'level_settings',
            ];

            /*
             * VERIFY 51 LEVELS IN BOTH TABLES
             */

            foreach ($tables as $table) {

                $levels = DB::table($table)
                    ->orderBy('level_number')
                    ->lockForUpdate()
                    ->pluck('level_number')
                    ->map(
                        fn ($level) => (int) $level
                    )
                    ->all();

                if ($levels !== range(1, 51)) {
                    throw new RuntimeException(
                        "Expected exactly 51 levels in {$table}."
                    );
                }
            }

            /*
             * APPLY NEW COMMISSION RATES
             *
             * 1. Investment Distribution
             * 2. Trading Profit Generation
             */

            foreach ($tables as $table) {

                foreach ($rates as $level => $rate) {

                    DB::table($table)
                        ->where('level_number', $level)
                        ->update([
                            'commission_percentage' => $rate,
                            'updated_at' => now(),
                        ]);
                }
            }

        }, 3);
    }

    /**
     * Financial configuration rollback is disabled.
     *
     * Previous rates must be restored using
     * an approved migration and verified backup.
     */

    public function down(): void
    {
        throw new RuntimeException(
            'Automatic rollback of commission rates is disabled.'
        );
    }
};