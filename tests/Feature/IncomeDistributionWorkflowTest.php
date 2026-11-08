<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncomeDistributionService;
use App\Services\StakingProfitService;
use App\Services\StakingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class IncomeDistributionWorkflowTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        FinancialSeedState::captureIfNeeded();
    }

    protected function afterTruncatingDatabase(): void
    {
        FinancialSeedState::restoreOrCapture();
    }

    public function test_independently_reserved_income_pays_self_once_and_holds_unqualified_network_shares(): void
    {
        $this->runSettlementWorkflow(false);
    }

    public function test_audited_same_admin_approval_pays_once_and_rejects_tampered_approval(): void
    {
        $this->runSettlementWorkflow(true);
        DB::table('member_security_audits')->where('action', 'profit_admin_verified')->delete();
        $admin = User::where('role', 'admin')->orderBy('id')->firstOrFail();
        $this->expectExceptionMessage('Recorded trading-profit approval is invalid.');
        IncomeDistributionService::distribute($admin, (int) DB::table('staking_profit_statements')->value('id'));
    }

    private function runSettlementWorkflow(bool $sameAdmin): void
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.staking_profit_enabled' => true, 'leobot.self_income_settlement_enabled' => true, 'leobot.generation_income_settlement_enabled' => true, 'leobot_magic.magic_matrix_settlement_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $reviewer = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        WalletService::credit($member->id, 'available_balance', '50', 'test_fixture', 'distribution_workflow_fixture');
        app(StakingService::class)->create($member->id, '50', 'distribution_workflow_stake');
        $id = IncomeDistributionService::submit($admin, '100', 'DISTRIBUTION-WORKFLOW-001', 'monthly', '2026-11-07');
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
        config(['leobot.admin_profit_verification_enabled' => true]);
        if ($sameAdmin) {
            StakingProfitService::verifyByAdmin($admin, $id);
        } else {
            StakingProfitService::approve($reviewer, $id);
        }
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['settlement_enabled' => true]);
        DB::table('magic_matrix_level_rates')->update(['pool_percent' => '5']);
        IncomeDistributionService::distribute($admin, $id);
        IncomeDistributionService::distribute($admin, $id);
        $this->assertSame('67.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('self_income_settlements', 1);
        $this->assertDatabaseCount('generation_allocation_runs', 1);
        $this->assertDatabaseCount('magic_income_entitlements', 20);
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'self_balance' => '0', 'magic_balance' => '3', 'total_self_settled' => '67']);
        $this->assertDatabaseHas('generation_pool_accounts', ['id' => 1, 'balance' => '30', 'total_paid' => '0']);
    }
}
