<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_contact_consent_audits', function (Blueprint $table) {
            $table->id();

            // Member whose consent was changed.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Previous and updated consent values.
            $table->json('old_consents');
            $table->json('new_consents');

            // Request information for security auditing.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Time of the consent change.
            $table->timestamps();

            $table->index([
                'user_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_contact_consent_audits');
    }
};
