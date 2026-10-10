<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_sharing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('self_profit_percent', 12, 9)->default(67);
            // Fail closed until admin funding approvals and tests are reviewed.
            $table->boolean('combined_funding_enabled')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('profit_sharing_settings')->insert([
            'id' => 1,
            'self_profit_percent' => '67.000000000',
            'combined_funding_enabled' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('profit_sharing_reserve_accounts', function (Blueprint $table) {
            $table->id();
            $table->decimal('self_balance', 20, 8)->default(0);
            $table->decimal('magic_balance', 20, 8)->default(0);
            $table->decimal('total_self_funded', 20, 8)->default(0);
            $table->decimal('total_magic_funded', 20, 8)->default(0);
            $table->timestamps();
        });
        DB::table('profit_sharing_reserve_accounts')->insert([
            'id' => 1, 'self_balance' => '0.00000000',
            'magic_balance' => '0.00000000',
            'total_self_funded' => '0.00000000',
            'total_magic_funded' => '0.00000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('profit_sharing_fundings', function (Blueprint $table) {
            $table->id();
            $table->string('profit_reference', 100)->unique();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('generation_funding_id')->unique()
                ->constrained('generation_profit_fundings')->restrictOnDelete();
            $table->decimal('verified_profit_amount', 20, 8);
            $table->decimal('self_percent_snapshot', 12, 9);
            $table->decimal('generation_percent_snapshot', 12, 9);
            $table->decimal('magic_percent_snapshot', 12, 9);
            $table->decimal('self_amount', 20, 8);
            $table->decimal('generation_amount', 20, 8);
            $table->decimal('magic_amount', 20, 8);
            $table->decimal('funded_total', 20, 8);
            $table->decimal('company_rounding_remainder', 20, 8)->default(0);
            $table->string('status', 40)->default('reserved_no_payout');
            $table->timestamps();
        });

        Schema::create('profit_sharing_self_reserves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_id')->constrained('profit_sharing_fundings')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('member_profit_amount', 20, 8);
            $table->decimal('self_share_reserved', 20, 8);
            $table->string('status', 40)->default('reserved_no_payout');
            $table->timestamps();
            $table->unique(['funding_id', 'user_id']);
        });
    }

    public function down(): void
    {
        // Do not destroy any financial history during rollback.
        if (Schema::hasTable('profit_sharing_fundings') &&
            DB::table('profit_sharing_fundings')->exists()) {
            throw new \RuntimeException('Funded profit sharing exists. Rollback forbidden.');
        }
        Schema::dropIfExists('profit_sharing_self_reserves');
        Schema::dropIfExists('profit_sharing_fundings');
        Schema::dropIfExists('profit_sharing_reserve_accounts');
        Schema::dropIfExists('profit_sharing_settings');
    }
};
