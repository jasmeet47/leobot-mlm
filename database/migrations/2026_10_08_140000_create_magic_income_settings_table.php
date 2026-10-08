<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_income_settings', function (Blueprint $table) {
            $table->id();

            /*
             * GLOBAL MAGIC INCOME CONTROL
             *
             * OFF by default for safety.
             */
            $table->boolean('magic_enabled')
                ->default(false);

            /*
             * MINIMUM ACTIVATION THRESHOLD
             *
             * Default: 50 USDT
             *
             * Eligibility rule:
             * A member must have at least one
             * ACTIVE activation package with
             * investment_amount > minimum_activation_usdt.
             *
             * Example:
             * 50 USDT = Not eligible
             * 60 USDT = Eligible
             *
             * Smaller packages must NOT be combined.
             */
            $table->decimal(
                'minimum_activation_usdt',
                20,
                8
            )->default(50);

            /*
             * MAGIC INCOME FUNDING POOL
             *
             * Default: 3% of eligible trading profit.
             *
             * Can be updated by Admin later.
             */
            $table->decimal(
                'trading_profit_pool_percent',
                12,
                9
            )->default(3);

            /*
             * Last Admin who changed the settings.
             */
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        /*
         * Create initial global settings.
         * Magic Income starts OFF.
         */
        DB::table('magic_income_settings')->insert([
            'id' => 1,
            'magic_enabled' => false,
            'minimum_activation_usdt' => 50,
            'trading_profit_pool_percent' => 3,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_income_settings');
    }
};
