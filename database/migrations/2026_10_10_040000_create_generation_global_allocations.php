<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable, one-shot snapshot per independently funded profit period.
        Schema::create('generation_allocation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_id')
                ->unique()
                ->constrained('generation_profit_fundings')
                ->restrictOnDelete();
            $table->foreignId('approved_by')
                ->constrained('users')->restrictOnDelete();
            $table->string('policy_version', 50)->default('network_business_v1');
            $table->string('status', 40)->default('classified_no_payout');
            $table->decimal('payable_total', 20, 8)->default(0);
            $table->decimal('held_total', 20, 8)->default(0);
            $table->decimal('unallocated_total', 20, 8)->default(0);
            $table->unsignedInteger('candidate_rows')->default(0);
            $table->timestamp('snapshot_at');
            $table->timestamps();
        });

        // Individual, auditable candidate shares. Statuses are snapshots only.
        Schema::create('generation_member_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_run_id')
                ->constrained('generation_allocation_runs')->restrictOnDelete();
            $table->foreignId('funding_id')
                ->constrained('generation_profit_fundings')->restrictOnDelete();
            $table->foreignId('level_budget_id')
                ->constrained('generation_level_budgets')->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('level_number');
            $table->decimal('network_business_snapshot', 20, 8);
            $table->decimal('share_amount', 20, 8);
            $table->unsignedTinyInteger('unlocked_through_level_snapshot');
            $table->string('classification', 20); // held | payable
            $table->string('qualification_reason', 50);
            $table->string('status', 40); // held_unqualified | qualified_reserved_no_payout
            $table->timestamps();
            $table->unique(['funding_id', 'level_number', 'user_id'], 'gen_member_alloc_once');
            $table->index(['allocation_run_id', 'classification']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('generation_allocation_runs')
            && DB::table('generation_allocation_runs')->exists()) {
            throw new \RuntimeException('Allocations exist: automatic rollback forbidden.');
        }
        Schema::dropIfExists('generation_member_allocations');
        Schema::dropIfExists('generation_allocation_runs');
    }
};
