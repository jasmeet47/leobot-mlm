<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_sharing_settings', function (Blueprint $table): void {
            $table->string('distribution_policy', 40)->default('unified_67_30_3');
        });
        Schema::table('profit_sharing_fundings', function (Blueprint $table): void {
            $table->string('magic_distribution_version', 40)->default('matrix_v1');
            $table->decimal('matrix_percent_snapshot', 12, 9)->nullable();
            $table->decimal('equal_percent_snapshot', 12, 9)->nullable();
            $table->json('matrix_rates_snapshot')->nullable();
            $table->json('equal_members_snapshot')->nullable();
        });
        Schema::create('magic_equal_settlement_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funding_id')->unique()->constrained('profit_sharing_fundings')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->decimal('budget_amount', 20, 8);
            $table->decimal('paid_amount', 20, 8);
            $table->decimal('held_amount', 20, 8);
            $table->unsignedInteger('member_count');
            $table->timestamps();
        });
        Schema::create('magic_equal_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('magic_equal_settlement_runs')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('magic_matrix_cycles')->restrictOnDelete();
            $table->decimal('nominal_amount', 20, 8);
            $table->decimal('paid_amount', 20, 8);
            $table->decimal('held_amount', 20, 8);
            $table->string('status', 32);
            $table->string('transaction_key', 150)->unique();
            $table->timestamps();
            $table->unique(['run_id', 'user_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('magic_equal_settlement_runs')->exists() || DB::table('profit_sharing_fundings')->where('magic_distribution_version', 'matrix_equal_v2')->exists()) {
            throw new RuntimeException('Versioned financial records exist; rollback prohibited.');
        }
        Schema::dropIfExists('magic_equal_entitlements');
        Schema::dropIfExists('magic_equal_settlement_runs');
        Schema::table('profit_sharing_fundings', fn (Blueprint $table) => $table->dropColumn(['magic_distribution_version', 'matrix_percent_snapshot', 'equal_percent_snapshot', 'matrix_rates_snapshot', 'equal_members_snapshot']));
        Schema::table('profit_sharing_settings', fn (Blueprint $table) => $table->dropColumn('distribution_policy'));
    }
};
