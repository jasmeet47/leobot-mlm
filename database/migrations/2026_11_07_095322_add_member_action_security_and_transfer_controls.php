<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('magic_matrix_settings')->where('id', 1)->first();
        if ($settings && bccomp((string) $settings->minimum_tier_usdt, '50', 8) !== 0) {
            $owner = DB::table('users')->where('id', 1)->whereIn('role', ['admin', 'super_admin'])->where('status', 'active')->where('is_blocked', false)->whereNull('deleted_at')->first();
            if (! $owner) {
                throw new RuntimeException('An authorized owner is required for the requested Magic threshold correction.');
            }
            DB::table('magic_matrix_settings')->where('id', 1)->update(['minimum_tier_usdt' => '50', 'updated_by' => $owner->id, 'updated_at' => now()]);
            DB::table('magic_matrix_settings_audits')->insert(['admin_user_id' => $owner->id, 'action' => 'minimum_50_correction', 'before_json' => json_encode(['minimum_tier_usdt' => $settings->minimum_tier_usdt]), 'after_json' => json_encode(['minimum_tier_usdt' => '50']), 'created_at' => now()]);
        }
        Schema::create('member_action_challenges', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('action', 30);
            $t->string('context_digest', 64);
            $t->string('email_digest', 64);
            $t->string('otp_hash');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'action', 'created_at']);
        });
        Schema::create('member_transfer_settings', function (Blueprint $t): void {
            $t->id();
            $t->boolean('enabled')->default(false);
            $t->decimal('minimum_usdt', 20, 8)->default(1);
            $t->decimal('maximum_usdt', 20, 8)->nullable();
            $t->timestamps();
        });
        DB::table('member_transfer_settings')->insert(['id' => 1, 'enabled' => false, 'minimum_usdt' => '1', 'maximum_usdt' => null, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('member_transfer_receipts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $t->decimal('amount', 20, 8);
            $t->string('request_key', 100)->unique();
            $t->timestamp('created_at');
        });
        Schema::create('income_distribution_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('statement_id')->unique()->constrained('staking_profit_statements')->restrictOnDelete();
            $t->string('period', 10);
            $t->date('period_date');
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_distribution_requests');
        Schema::dropIfExists('member_transfer_receipts');
        Schema::dropIfExists('member_transfer_settings');
        Schema::dropIfExists('member_action_challenges');
    }
};
