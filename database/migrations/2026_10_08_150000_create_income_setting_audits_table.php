<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_setting_audits', function (Blueprint $table) {
            $table->id();

            /*
             * Admin who changed the settings.
             */
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('admin_username', 100);

            /*
             * Settings before and after the change.
             *
             * Includes:
             * - ROI ON/OFF
             * - ROI distribution mode
             * - ROI percentage
             * - Magic Income ON/OFF
             * - Minimum activation USDT
             * - Magic Pool percentage
             */
            $table->json('old_settings');
            $table->json('new_settings');

            /*
             * Admin activity information.
             */
            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->timestamps();

            /*
             * Useful for audit history searches.
             */
            $table->index([
                'admin_user_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_setting_audits');
    }
};
