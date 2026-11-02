<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::table('staking_positions', function(Blueprint $t) {
            $t->decimal('remaining_amount', 20, 8)->nullable();
        });
        DB::table('staking_positions')->whereNull('remaining_amount')->update(['remaining_amount'=>DB::raw('amount')]);
        Schema::create('staking_unstake_requests', function(Blueprint $t) {
            $t->id();
            $t->foreignId('position_id')->constrained('staking_positions');
            $t->foreignId('user_id')->constrained('users');
            $t->decimal('amount',20,8);
            $t->string('request_key',100)->unique();
            $t->string('status',20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['position_id','status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('staking_unstake_requests');
        Schema::table('staking_positions', fn(Blueprint $t) => $t->dropColumn('remaining_amount'));
    }
};
