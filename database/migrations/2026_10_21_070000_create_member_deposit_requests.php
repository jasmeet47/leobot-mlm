<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('member_deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('request_key', 100)->unique();
            $table->decimal('amount', 20, 8);
            $table->string('network', 10);
            $table->string('tx_hash', 150);
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('verification_reference', 150)->nullable()->unique();
            $table->timestamps();
            $table->unique(['network','tx_hash']);
            $table->index(['user_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('member_deposit_requests'); }
};
