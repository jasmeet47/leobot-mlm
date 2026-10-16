<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('request_key', 100)->unique();
            $table->string('wallet_type', 40);
            $table->decimal('gross_amount', 20, 8);
            $table->decimal('fee_amount', 20, 8)->default(0);
            $table->decimal('net_amount', 20, 8);
            $table->string('status', 30)->default('requested');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('external_payment_reference', 150)->nullable()->unique();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('withdrawal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->constrained('withdrawal_requests')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('description')->nullable();
            $table->timestamp('created_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('withdrawal_events');
        Schema::dropIfExists('withdrawal_requests');
    }
};
