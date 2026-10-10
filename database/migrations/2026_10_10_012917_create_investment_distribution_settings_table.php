<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Future Investment Distribution Module.
         *
         * Default: OFF.
         *
         * Commission budget is calculated as a
         * percentage of the investment amount.
         *
         * The member's recorded principal is NOT
         * reduced by this calculation.
         *
         * Actual payouts must be funded separately
         * from verified, available company funds.
         *
         * This migration NEVER distributes money.
         */

        Schema::create(
            'investment_distribution_settings',
            function (Blueprint $table) {
                $table->id();

                $table->boolean('is_enabled')
                    ->default(false);

                $table->decimal(
                    'distribution_percent',
                    12,
                    9
                )->default(5);

                $table->unsignedTinyInteger(
                    'max_distribution_level'
                )->default(51);

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();
            }
        );

        /*
         * Independent per-level distribution rates.
         *
         * These rates describe how the configured
         * investment distribution budget is shared
         * between Levels 1 to 51.
         *
         * They are separate from Trading Profit
         * Generation Pool rates.
         */

        Schema::create(
            'investment_distribution_level_rates',
            function (Blueprint $table) {
                $table->id();

                $table->unsignedTinyInteger(
                    'level_number'
                )->unique();

                $table->decimal(
                    'commission_percentage',
                    12,
                    9
                )->default(0);

                $table->boolean('is_active')
                    ->default(true);

                $table->timestamps();
            }
        );

        /*
         * Insert initial global settings.
         *
         * Feature remains OFF until explicitly
         * approved, configured and tested.
         */

        DB::table(
            'investment_distribution_settings'
        )->insert([
            'id' => 1,
            'is_enabled' => false,
            'distribution_percent' => '5.000000000',
            'max_distribution_level' => 51,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Copy the existing 51-Level rate structure
         * as a starting configuration.
         *
         * Future changes to Trading Profit rates
         * will NOT automatically change these rates.
         */

        $levels = DB::table('level_settings')
            ->orderBy('level_number')
            ->get();

        if (
            $levels->count() !== 51 ||
            $levels->pluck('level_number')
                ->map(fn ($level) => (int) $level)
                ->all() !== range(1, 51)
        ) {
            throw new RuntimeException(
                'Exactly 51 source level settings are required.'
            );
        }

        foreach ($levels as $level) {
            DB::table(
                'investment_distribution_level_rates'
            )->insert([
                'level_number' =>
                    (int) $level->level_number,

                'commission_percentage' =>
                    (string) $level->commission_percentage,

                'is_active' =>
                    (bool) $level->is_active,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'investment_distribution_level_rates'
        );

        Schema::dropIfExists(
            'investment_distribution_settings'
        );
    }
};
