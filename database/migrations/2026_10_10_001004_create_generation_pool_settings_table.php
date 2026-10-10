<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_pool_settings', function (Blueprint $table) {
            $table->id();

            // Disabled until the 51-level engine is tested.
            $table->boolean('generation_enabled')
                ->default(false);

            // Default: 30% of actual trading profit.
            // Admin can change this later.
            $table->decimal(
                'trading_profit_pool_percent',
                12,
                9
            )->default(30);

            // Record the last admin who updated settings.
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        DB::table('generation_pool_settings')->insert([
            'id' => 1,
            'generation_enabled' => false,
            'trading_profit_pool_percent' => '30.000000000',
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_pool_settings');
    }
};
