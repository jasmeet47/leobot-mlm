<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_privacy_settings', function (Blueprint $table) {
            $table->id();

            // Phone and email visibility: off, masked, full
            $table->string('phone_visibility', 10)->default('off');
            $table->string('email_visibility', 10)->default('off');

            // Investment and package details: OFF by default
            $table->boolean('show_investment_amount')->default(false);
            $table->boolean('show_package_status')->default(false);

            $table->timestamps();
        });

        DB::table('referral_privacy_settings')->insert([
            'id' => 1,
            'phone_visibility' => 'off',
            'email_visibility' => 'off',
            'show_investment_amount' => false,
            'show_package_status' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_privacy_settings');
    }
};
