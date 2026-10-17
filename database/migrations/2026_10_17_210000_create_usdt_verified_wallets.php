<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usdt_member_wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('network', 8);
            $t->string('address', 64);
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->string('verification_method', 40)->nullable();
            $t->string('proof_digest', 64)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'network', 'address']);
        });
        Schema::table('withdrawal_requests', function (Blueprint $t) {
            $t->foreignId('usdt_member_wallet_id')->nullable()->constrained('usdt_member_wallets')->restrictOnDelete();
            $t->string('payment_network', 8)->nullable();
            $t->string('locked_payment_address', 64)->nullable();
        });
    }
    public function down(): void {
        throw new RuntimeException('Rollback disabled: withdrawal recipient audit history must be preserved.');
    }
};
