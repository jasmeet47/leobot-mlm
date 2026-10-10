<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single funded reserve for the company-wide generation pool.
        // Invariant for this phase: balance == unallocated_balance.
        // Held/payable start at zero until recipient allocation rules are approved.
        Schema::create('generation_pool_accounts', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('unallocated_balance', 20, 8)->default(0);
            $table->decimal('held_balance', 20, 8)->default(0);
            $table->decimal('payable_balance', 20, 8)->default(0);
            $table->decimal('total_funded', 20, 8)->default(0);
            $table->decimal('total_paid', 20, 8)->default(0);
            $table->timestamps();
        });

        DB::table('generation_pool_accounts')->insert([
            'id' => 1,
            'balance' => '0.00000000',
            'unallocated_balance' => '0.00000000',
            'held_balance' => '0.00000000',
            'payable_balance' => '0.00000000',
            'total_funded' => '0.00000000',
            'total_paid' => '0.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('generation_profit_fundings', function (Blueprint $table) {
            $table->id();
            // Unique source document/period ref, NOT only an HTTP retry UUID.
            // Prevents funding one trading-profit statement more than once.
            $table->string('profit_reference', 100)->unique();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->decimal('verified_profit_amount', 20, 8);
            $table->decimal('pool_percentage_snapshot', 12, 9);
            $table->decimal('pool_amount', 20, 8);
            $table->decimal('rounding_unallocated', 20, 8)->default(0);
            $table->string('status', 30)->default('funded_unallocated');
            $table->timestamps();
        });

        Schema::create('generation_level_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_id')
                ->constrained('generation_profit_fundings')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('level_number');
            $table->decimal('rate_percent_snapshot', 12, 9);
            $table->boolean('was_active')->default(false);
            $table->decimal('budget_amount', 20, 8);
            $table->decimal('unallocated_amount', 20, 8);
            $table->decimal('held_amount', 20, 8)->default(0);
            $table->decimal('payable_amount', 20, 8)->default(0);
            $table->decimal('paid_amount', 20, 8)->default(0);
            $table->timestamps();

            $table->unique(['funding_id', 'level_number']);
            $table->index('level_number');
        });
    }

    public function down(): void
    {
        // Never silently delete funded financial records in a rollback.
        if (Schema::hasTable('generation_profit_fundings')
            && DB::table('generation_profit_fundings')->exists()) {
            throw new \RuntimeException(
                'Generation funding exists; automatic rollback is forbidden.'
            );
        }

        if (Schema::hasTable('generation_pool_accounts')) {
            $pool = DB::table('generation_pool_accounts')->where('id', 1)->first();
            if ($pool && (
                bccomp((string) $pool->balance, '0', 8) !== 0 ||
                bccomp((string) $pool->unallocated_balance, '0', 8) !== 0 ||
                bccomp((string) $pool->held_balance, '0', 8) !== 0 ||
                bccomp((string) $pool->payable_balance, '0', 8) !== 0 ||
                bccomp((string) $pool->total_funded, '0', 8) !== 0 ||
                bccomp((string) $pool->total_paid, '0', 8) !== 0
            )) {
                throw new \RuntimeException('Nonzero generation reserve; rollback forbidden.');
            }
        }

        Schema::dropIfExists('generation_level_budgets');
        Schema::dropIfExists('generation_profit_fundings');
        Schema::dropIfExists('generation_pool_accounts');
    }
};
