<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_contact_consents', function (Blueprint $table) {
            $table->id();

            // Member who owns the contact information.
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Both permissions are OFF by default.
            $table->boolean('phone_consent')->default(false);
            $table->boolean('email_consent')->default(false);

            // When each permission was last granted.
            $table->timestamp('phone_consented_at')->nullable();
            $table->timestamp('email_consented_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_contact_consents');
    }
};
