<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_pool_fundings', function (Blueprint $table) {
            $table->id();

            // Source references are normalized to uppercase in the service.
            // Unique across BOTH funding modes in this table.
            $table->string('source_reference', 100)->unique();

            // 'trading_profit' or 'direct_pool'; validated by the service.
            $table->string('funding_mode', 32);

            $table->foreignId('approved_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Only trading_profit mode uses these fields; keep a historical rate snapshot.
            $table->decimal('verified_profit_amount', 20, 8)->nullable();
            $table->decimal('magic_percent_snapshot', 12, 9)->nullable();

            // Amount actually transferred from the company wallet to the magic reserve.
            $table->decimal('pool_amount', 20, 8);
            $table->string('status', 40)->default('reserved_no_payout');
            $table->timestamps();

            $table->index(['funding_mode', 'created_at']);
        });
    }

    public function down(): void
    {
        // Never silently erase a financial audit trail.
        if (Schema::hasTable('magic_pool_fundings') &&
            DB::table('magic_pool_fundings')->exists()) {
            throw new RuntimeException(
                'Magic pool funding records exist. Rollback forbidden.'
            );
        }

        Schema::dropIfExists('magic_pool_fundings');
    }
};
