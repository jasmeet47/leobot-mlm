<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('withdrawal_payment_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->unique()->constrained('withdrawal_requests')->restrictOnDelete();
            $table->string('network', 8);
            $table->string('tx_hash', 66);
            $table->string('recipient', 64);
            $table->decimal('amount', 20, 8);
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->unique(['network', 'tx_hash'], 'withdrawal_evidence_network_hash_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback prohibited: immutable blockchain payment evidence.');
    }
};
