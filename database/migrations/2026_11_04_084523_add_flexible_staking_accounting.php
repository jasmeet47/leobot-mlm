<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staking_unstake_requests') && DB::table('staking_unstake_requests')->exists()) {
            throw new RuntimeException('Legacy unstake history requires independent reconciliation before this migration.');
        }
        if (Schema::hasColumn('staking_positions', 'remaining_amount') && DB::table('staking_positions')
            ->where(function ($query): void {
                $query->whereNull('remaining_amount')->orWhereColumn('remaining_amount', '<>', 'amount');
            })->exists()) {
            throw new RuntimeException('Legacy remaining principal requires independent reconciliation before this migration.');
        }

        Schema::table('staking_positions', function (Blueprint $table) {
            $table->string('funding_wallet', 40)->default('available_balance');
            $table->decimal('withdrawn_amount', 20, 8)->default(0);
        });
        DB::statement('ALTER TABLE staking_positions ADD CONSTRAINT staking_principal_bounds CHECK (withdrawn_amount >= 0 AND withdrawn_amount <= amount)');
        Schema::table('withdrawal_settings', function (Blueprint $table) {
            $table->timestamp('principal_fee_configured_at')->nullable();
        });
        Schema::table('member_deposit_requests', fn (Blueprint $table) => $table->string('receiving_address', 100)->nullable());
        Schema::create('staking_principal_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->unique()->constrained('withdrawal_requests')->restrictOnDelete();
            $table->foreignId('staking_position_id')->constrained('staking_positions')->restrictOnDelete();
            $table->decimal('fee_percent_snapshot', 12, 9);
            $table->timestamps();
        });
        Schema::create('staking_profit_statements', function (Blueprint $table) {
            $table->id();
            $table->string('evidence_reference', 150)->unique();
            $table->decimal('amount', 20, 8);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('pending_verification');
            $table->timestamps();
        });
        Schema::create('staking_profit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained('staking_profit_statements')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 20, 8);
            $table->unique(['statement_id', 'user_id']);
            $table->timestamps();
        });
        Schema::create('withdrawal_payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->unique()->constrained('withdrawal_requests')->restrictOnDelete();
            $table->string('network', 10);
            $table->string('tx_hash', 66);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->unique(['network', 'tx_hash']);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE staking_profit_statements ADD CONSTRAINT staking_profit_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE staking_profit_allocations ADD CONSTRAINT staking_profit_allocation_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE staking_principal_withdrawals ADD CONSTRAINT staking_fee_snapshot_bounds CHECK (fee_percent_snapshot >= 0 AND fee_percent_snapshot < 100)');
    }

    public function down(): void
    {
        if (DB::table('staking_positions')->exists() || DB::table('member_deposit_requests')->whereNotNull('receiving_address')->exists()) {
            throw new RuntimeException('Staking or treasury history exists; destructive rollback prohibited.');
        }
        foreach (['staking_principal_withdrawals', 'staking_profit_statements', 'withdrawal_payment_submissions'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Financial history exists; destructive rollback prohibited.');
            }
        }
        Schema::dropIfExists('withdrawal_payment_submissions');
        Schema::dropIfExists('staking_profit_allocations');
        Schema::dropIfExists('staking_profit_statements');
        Schema::dropIfExists('staking_principal_withdrawals');
        Schema::table('withdrawal_settings', fn (Blueprint $table) => $table->dropColumn('principal_fee_configured_at'));
        Schema::table('member_deposit_requests', fn (Blueprint $table) => $table->dropColumn('receiving_address'));
        DB::statement('ALTER TABLE staking_positions DROP CONSTRAINT staking_principal_bounds');
        Schema::table('staking_positions', fn (Blueprint $table) => $table->dropColumn(['funding_wallet', 'withdrawn_amount']));
    }
};
