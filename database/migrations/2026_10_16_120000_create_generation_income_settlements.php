<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('generation_income_settlements', function(Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->unique()->constrained('generation_member_allocations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount',20,8);
            $table->string('transaction_key',255)->unique();
            $table->timestamps();
        });
    }
    public function down(): void {
        if (Schema::hasTable('generation_income_settlements') && DB::table('generation_income_settlements')->exists()) {
            throw new RuntimeException('Financial settlement history exists; rollback prohibited.');
        }
        Schema::dropIfExists('generation_income_settlements');
    }
};
