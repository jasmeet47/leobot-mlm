<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('registration_email_otp_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
        DB::table('registration_email_otp_settings')->insert([
            'id' => 1, 'is_enabled' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('registration_email_otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index();
            $table->string('otp_hash', 255);
            $table->char('challenge_token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
        Schema::create('registration_email_otp_setting_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable();
            $table->boolean('old_value');
            $table->boolean('new_value');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('registration_email_otp_setting_audits');
        Schema::dropIfExists('registration_email_otp_challenges');
        Schema::dropIfExists('registration_email_otp_settings');
    }
};
