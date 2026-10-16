<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_matrix_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('enrollment_enabled')->default(false);
            $t->boolean('topup_enabled')->default(false);
            $t->boolean('settlement_enabled')->default(false);
            $t->decimal('minimum_tier_usdt', 20, 8)->default(50);
            $t->unsignedTinyInteger('earning_cap_multiplier')->default(3);
            $t->unsignedSmallInteger('grace_days')->default(6);
            $t->unsignedTinyInteger('max_depth')->default(20);
            $t->string('frequency', 16)->default('manual');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        DB::table('magic_matrix_settings')->insert([
            'id' => 1, 'minimum_tier_usdt' => '50.00000000',
            'earning_cap_multiplier' => 3, 'grace_days' => 6, 'max_depth' => 20,
            'frequency' => 'manual', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('magic_matrix_settings_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->text('before_json');
            $t->text('after_json');
            $t->timestamp('created_at');
        });

        Schema::create('magic_matrix_level_rates', function (Blueprint $t) {
            $t->unsignedTinyInteger('level')->primary();
            $t->decimal('pool_percent', 12, 9)->default(0);
            $t->timestamps();
        });
        for ($level = 1; $level <= 20; $level++) {
            DB::table('magic_matrix_level_rates')->insert([
                'level' => $level, 'pool_percent' => '0.000000000',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('magic_matrix_positions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('magic_matrix_positions')->restrictOnDelete();
            $t->unsignedTinyInteger('slot')->nullable(); // 1 or 2, null for root
            $t->unsignedTinyInteger('depth'); // root=0, maximum=20
            $t->timestamp('placed_at');
            $t->timestamps();
            $t->unique(['parent_id', 'slot']);
            $t->index(['depth', 'id']);
        });
        Schema::create('magic_matrix_cycles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('position_id')->constrained('magic_matrix_positions')->restrictOnDelete();
            $t->foreignId('activation_package_id')->nullable()->unique()->constrained('activation_packages')->restrictOnDelete();
            $t->string('source_reference', 100)->unique();
            $t->decimal('tier_usdt', 20, 8); // normal recurring tier, unchanged by late fee
            $t->decimal('earning_basis_usdt', 20, 8); // actual verified topup for this cycle
            $t->unsignedTinyInteger('earning_multiplier');
            $t->decimal('earning_cap_usdt', 20, 8);
            $t->decimal('magic_paid_usdt', 20, 8)->default(0);
            $t->string('status', 20)->default('active'); // active, capped
            $t->timestamp('cap_reached_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });
        Schema::create('company_magic_wallet', function (Blueprint $t) {
            $t->id();
            $t->decimal('balance', 20, 8)->default(0);
            $t->decimal('total_verified_topups', 20, 8)->default(0);
            $t->timestamps();
        });
        DB::table('company_magic_wallet')->insert([
            'id' => 1, 'balance' => '0.00000000',
            'total_verified_topups' => '0.00000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('magic_matrix_topups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('previous_cycle_id')->unique()->constrained('magic_matrix_cycles')->restrictOnDelete();
            $t->foreignId('new_cycle_id')->unique()->nullable()->constrained('magic_matrix_cycles')->restrictOnDelete();
            $t->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $t->string('payment_reference', 100)->unique(); // verified unique external receipt
            $t->decimal('base_tier_usdt', 20, 8);
            $t->unsignedInteger('delay_multiplier');
            $t->decimal('received_usdt', 20, 8);
            $t->timestamp('verified_at');
            $t->timestamps();
        });
        Schema::create('company_magic_wallet_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('topup_id')->unique()->constrained('magic_matrix_topups')->restrictOnDelete();
            $t->string('transaction_key', 128)->unique();
            $t->decimal('amount', 20, 8);
            $t->decimal('balance_before', 20, 8);
            $t->decimal('balance_after', 20, 8);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['company_magic_wallet_ledger', 'magic_matrix_topups', 'magic_matrix_cycles', 'magic_matrix_positions', 'magic_matrix_settings_audits'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new \RuntimeException('Magic financial or placement history exists: rollback prohibited.');
            }
        }
        Schema::dropIfExists('company_magic_wallet_ledger');
        Schema::dropIfExists('magic_matrix_topups');
        Schema::dropIfExists('company_magic_wallet');
        Schema::dropIfExists('magic_matrix_cycles');
        Schema::dropIfExists('magic_matrix_positions');
        Schema::dropIfExists('magic_matrix_settings_audits');
        Schema::dropIfExists('magic_matrix_level_rates');
        Schema::dropIfExists('magic_matrix_settings');
    }
};
