<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('magic_income_entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('funding_id')->constrained('profit_sharing_fundings')->restrictOnDelete();
            $t->foreignId('source_user_id')->constrained('users')->restrictOnDelete();
            $t->unsignedTinyInteger('level');
            $t->foreignId('beneficiary_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('cycle_id')->nullable()->constrained('magic_matrix_cycles')->restrictOnDelete();
            $t->decimal('pool_percent_snapshot',12,9);
            $t->decimal('nominal_amount',20,8);
            $t->decimal('paid_amount',20,8)->default(0);
            $t->decimal('held_amount',20,8)->default(0);
            $t->string('status',32); // credited, held_missing, held_ineligible, held_cap, held_dust
            $t->string('transaction_key',150)->unique();
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['funding_id','source_user_id','level']);
            $t->index(['beneficiary_user_id','status']);
        });
    }
    public function down(): void {
        if (Schema::hasTable('magic_income_entitlements') && DB::table('magic_income_entitlements')->exists()) {
            throw new RuntimeException('Historical Magic entitlements exist: rollback prohibited.');
        }
        Schema::dropIfExists('magic_income_entitlements');
    }
};
