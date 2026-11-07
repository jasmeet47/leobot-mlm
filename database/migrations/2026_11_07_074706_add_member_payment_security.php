<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_security_controls', function (Blueprint $t): void {
            $t->id();
            $t->boolean('wallet_changes_enabled')->default(true);
            $t->timestamps();
        });
        DB::table('member_security_controls')->insert(['id' => 1, 'wallet_changes_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('member_wallet_profiles', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('network', 10);
            $t->foreignId('wallet_id')->constrained('usdt_member_wallets')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'network']);
        });
        Schema::create('member_security_audits', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 50);
            $t->json('details');
            $t->timestamp('created_at');
        });
        Schema::create('withdrawal_email_challenges', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('context_digest', 64);
            $t->string('email_digest', 64);
            $t->string('otp_hash');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_email_challenges');
        Schema::dropIfExists('member_wallet_profiles');
        Schema::dropIfExists('member_security_audits');
        Schema::dropIfExists('member_security_controls');
    }
};
