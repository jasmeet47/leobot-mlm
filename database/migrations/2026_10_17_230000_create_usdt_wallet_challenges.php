<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usdt_wallet_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('network', 8);
            $table->string('address', 64);
            $table->char('nonce_hash', 64)->unique();
            $table->char('message_hash', 64);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'expires_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('usdt_wallet_challenges'); }
};
