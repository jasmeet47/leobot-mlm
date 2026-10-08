<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roi_settings', function (Blueprint $table) {
            $table->id();

            // Global ROI switch: OFF by default.
            $table->boolean('roi_enabled')->default(false);

            // daily, weekly, monthly, manual
            $table->string('distribution_mode', 20)
                ->default('manual');

            // Optional global percentage.
            // Package-specific percentages take priority.
            $table->decimal('default_roi_percentage', 12, 9)
                ->default(0);

            // Prevent overlapping distribution runs.
            $table->boolean('distribution_locked')
                ->default(false);

            // Record the latest successful run.
            $table->timestamp('last_distributed_at')
                ->nullable();

            // Track who changed the settings.
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        // Create exactly one global settings record.
        // ROI remains OFF until Admin enables it.
        DB::table('roi_settings')->insert([
            'id' => 1,
            'roi_enabled' => false,
            'distribution_mode' => 'manual',
            'default_roi_percentage' => 0,
            'distribution_locked' => false,
            'last_distributed_at' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roi_settings');
    }
};
