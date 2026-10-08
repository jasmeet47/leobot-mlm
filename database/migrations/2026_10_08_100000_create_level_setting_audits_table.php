<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_setting_audits', function (Blueprint $table) {
            $table->id();

            // Admin who changed the settings
            $table->unsignedBigInteger('admin_user_id');

            // Keep the username for audit history
            $table->string('admin_username', 255);

            // Settings before and after the change
            $table->json('old_settings');
            $table->json('new_settings');

            // Request details
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Date and time
            $table->timestamps();

            $table->index([
                'admin_user_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_setting_audits');
    }
};
