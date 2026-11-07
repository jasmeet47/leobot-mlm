<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DisposableDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class DeployedStakingCompatibilityTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        DisposableDatabase::assertSafe();
        FinancialSeedState::captureIfNeeded();
    }

    protected function afterTruncatingDatabase(): void
    {
        FinancialSeedState::restoreOrCapture();
    }

    public function test_migration_refuses_to_reopen_principal_already_returned_by_deployed_legacy_code(): void
    {
        $migration = require database_path('migrations/2026_11_04_084523_add_flexible_staking_accounting.php');
        Schema::dropIfExists('income_distribution_requests');
        foreach (['staking_profit_reviews', 'trading_profit_reviewer_audits', 'trading_profit_reviewers'] as $table) {
            Schema::dropIfExists($table);
        }
        $migration->down();
        Schema::table('staking_positions', fn (Blueprint $table) => $table->decimal('remaining_amount', 20, 8)->nullable());
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'available_balance' => '40']);
        $id = DB::table('staking_positions')->insertGetId(['user_id' => $member->id, 'amount' => '100', 'remaining_amount' => '60',
            'request_key' => 'deployed_legacy_stake', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        try {
            try {
                $migration->up();
                $this->fail('Legacy principal history was silently reopened');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Legacy remaining principal requires independent reconciliation', $e->getMessage());
                $this->assertDatabaseHas('staking_positions', ['id' => $id, 'amount' => '100.00000000', 'remaining_amount' => '60.00000000']);
                $this->assertSame('40.00000000', $member->fresh()->available_balance);
                $this->assertFalse(Schema::hasColumn('staking_positions', 'withdrawn_amount'));
            }
        } finally {
            // Disposable-fixture cleanup; production data is never rewritten by this migration.
            DB::table('staking_positions')->where('id', $id)->delete();
            Schema::table('staking_positions', fn (Blueprint $table) => $table->dropColumn('remaining_amount'));
            $migration->up();
            (require database_path('migrations/2026_11_05_012651_link_staking_profit_to_unified_funding.php'))->up();
            (require database_path('migrations/2026_11_05_024931_create_trading_profit_reviewers.php'))->up();
            Schema::create('income_distribution_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('statement_id')->unique()->constrained('staking_profit_statements');
                $table->string('period', 10);
                $table->date('period_date');
                $table->date('period_end_date')->nullable();
                $table->uuid('submission_token')->nullable()->unique();
                $table->foreignId('submitted_by')->constrained('users');
                $table->timestamp('created_at');
            });
        }
    }
}
