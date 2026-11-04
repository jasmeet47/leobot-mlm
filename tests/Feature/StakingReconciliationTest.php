<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingReconciliationService;
use App\Services\StakingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StakingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function stake(): int
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'activation_balance' => '100']);

        return (new StakingService)->create($member->id, '100', 'reconciliation_stake_01', 'activation_balance');
    }

    public function test_read_only_reconciliation_detects_missing_or_altered_stake_journals(): void
    {
        $id = $this->stake();
        $before = DB::table('financial_ledger')->count();
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
        $this->assertSame($before, DB::table('financial_ledger')->count());
        DB::table('financial_ledger')->where('reference_id', $id)->where('wallet_type', 'staking_principal')->update(['amount' => '99']);
        $report = StakingReconciliationService::inspect();
        $this->assertFalse($report['accounting_passed']);
        $this->assertGreaterThan(0, $report['issue_count']);
        $this->assertSame('NOT VERIFIED', $report['on_chain_reserve_solvency']);
    }

    public function test_reconciliation_rejects_unbacked_principal_reservations(): void
    {
        $id = $this->stake();
        DB::table('staking_positions')->where('id', $id)->update(['withdrawn_amount' => '1']);
        $report = StakingReconciliationService::inspect();
        $this->assertFalse($report['accounting_passed']);
        $this->assertStringContainsString('principal balance', implode(' ', $report['issues']));
    }

    public function test_readiness_reports_missing_external_configuration_without_enabling_anything(): void
    {
        $this->stake();
        $this->artisan('staking:readiness --json')->assertExitCode(1);
        $this->assertFalse(config('leobot.withdrawals_enabled'));
        $this->assertDatabaseCount('withdrawal_requests', 0);
    }
}
