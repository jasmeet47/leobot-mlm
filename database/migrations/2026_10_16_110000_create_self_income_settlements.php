<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('self_income_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_reserve_id')->unique()->constrained('profit_sharing_self_reserves')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 20, 8);
            $table->string('transaction_key', 255)->unique();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        if (Schema::hasTable('self_income_settlements') && DB::table('self_income_settlements')->exists()) {
            throw new RuntimeException('Settlements exist: rollback forbidden.');
        }
        Schema::dropIfExists('self_income_settlements');
    }
};
