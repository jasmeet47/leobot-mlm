<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_privacy_setting_audits', function (Blueprint $table) {
            $table->id();

            // Admin who changed the privacy settings.
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('admin_username', 100);

            // Complete settings before and after the change.
            $table->json('old_settings');
            $table->json('new_settings');

            // Admin activity details.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index([
                'admin_user_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_privacy_setting_audits');
    }
};
