<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingProfitService;
use App\Services\StakingReconciliationService;
use App\Services\StakingService;
use App\Services\UnifiedProfitSharingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StakingProfitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reviewer;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.staking_profit_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->reviewer = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->member = $this->staker();
    }

    private function staker(): User
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'activation_balance' => '100']);
        (new StakingService)->create($member->id, '100', 'profit_stake_'.$member->id.'_unique', 'activation_balance');

        return $member;
    }

    public function test_variable_profit_requires_independent_verification_and_credits_exactly_once(): void
    {
        $id = StakingProfitService::submit($this->admin, '7.12345678', 'TRADING-RECEIPT-001', [$this->member->id => '7.12345678']);
        $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
        StakingProfitService::approve($this->reviewer, $id);
        StakingProfitService::approve($this->reviewer, $id);
        $this->assertSame($id, StakingProfitService::submit($this->admin, '7.12345678', 'TRADING-RECEIPT-001', [$this->member->id => '7.12345678']));
        $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseHas('profit_sharing_fundings', ['verified_profit_amount' => '7.12345678', 'self_amount' => '4.77271604', 'generation_amount' => '2.13703703', 'magic_amount' => '0.21370370', 'company_rounding_remainder' => '0.00000001']);
        $this->assertDatabaseCount('profit_sharing_fundings', 1);
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'reserved', 'distribution_version' => 'unified_67_30_3']);
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
        $this->assertDatabaseHas('staking_positions', ['user_id' => $this->member->id, 'amount' => '100.00000000', 'withdrawn_amount' => '0.00000000']);
        $this->assertSame(2, DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->where('reference_id', $id)->count());
        $this->assertSame('0.00000000', (string) DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->where('reference_id', $id)->sum('amount'));
    }

    public function test_submitter_cannot_self_verify_profit(): void
    {
        $id = StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '10']);
        $this->expectException(AuthorizationException::class);
        StakingProfitService::approve($this->admin, $id);
    }

    public function test_allocations_cannot_exceed_actual_statement_profit(): void
    {
        $this->expectExceptionMessage('Member allocations must equal realized trading profit.');
        StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '11']);
    }

    public function test_reference_cannot_be_reused_with_changed_profit(): void
    {
        StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '10']);
        $this->expectExceptionMessage('Trading statement reference conflict.');
        StakingProfitService::submit($this->admin, '11', 'TRADING-RECEIPT-001', [$this->member->id => '11']);
    }

    public function test_partial_distribution_rolls_back_when_later_member_credit_fails(): void
    {
        $other = $this->staker();
        DB::table('profit_sharing_reserve_accounts')->where('id', 1)->update(['self_balance' => '999999999999.99999999', 'total_self_funded' => '999999999999.99999999']);
        $id = StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '5', $other->id => '5']);
        try {
            StakingProfitService::approve($this->reviewer, $id);
            $this->fail('Overflow accepted');
        } catch (\RuntimeException) {
            $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
            $this->assertSame('0.00000000', $other->fresh()->available_balance);
            $this->assertDatabaseCount('profit_sharing_fundings', 0);
            $this->assertDatabaseHas('company_wallet', ['id' => 1, 'balance' => '0.00000000']);
            $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'pending_verification', 'verified_by' => null]);
            $this->assertSame(0, DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->count());
        }
    }

    public function test_already_funded_reference_cannot_import_the_same_external_profit_twice(): void
    {
        DB::table('company_wallet')->where('id', 1)->update(['balance' => '10']);
        UnifiedProfitSharingService::fund($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '10']);
        $id = StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '10']);
        try {
            StakingProfitService::approve($this->reviewer, $id);
            $this->fail('Duplicate economic profit accepted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('already funded', $error->getMessage());
        }
        $this->assertDatabaseCount('profit_sharing_fundings', 1);
        $this->assertSame(0, DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->count());
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'pending_verification']);
    }

    public function test_wrong_split_or_disabled_combined_funding_cannot_partially_import_profit(): void
    {
        $id = StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '10']);
        foreach ([false, true] as $wrongSplit) {
            DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => $wrongSplit, 'self_profit_percent' => $wrongSplit ? '68' : '67']);
            try {
                StakingProfitService::approve($this->reviewer, $id);
                $this->fail('Unsafe distribution accepted.');
            } catch (\RuntimeException) {
                $this->assertDatabaseCount('profit_sharing_fundings', 0);
                $this->assertSame(0, DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->count());
                $this->assertDatabaseHas('company_wallet', ['id' => 1, 'balance' => '0.00000000']);
            }
        }
    }

    public function test_reconciliation_detects_tampered_pool_amount_and_missing_funding_journal(): void
    {
        $id = StakingProfitService::submit($this->admin, '100', 'TRADING-RECEIPT-001', [$this->member->id => '100']);
        StakingProfitService::approve($this->reviewer, $id);
        DB::table('profit_sharing_fundings')->update(['magic_amount' => '4']);
        DB::table('financial_ledger')->where('wallet_type', 'generation_pool')->delete();
        $report = StakingReconciliationService::inspect();
        $this->assertFalse($report['accounting_passed']);
        $this->assertGreaterThanOrEqual(2, $report['issue_count']);
    }

    public function test_no_fixed_reward_is_created_merely_by_holding_a_stake(): void
    {
        $this->travel(90)->days();
        $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseCount('staking_profit_statements', 0);
    }

    public function test_profit_gate_is_enforced_and_member_cannot_submit_statements(): void
    {
        $this->actingAs($this->member)->post(route('admin.staking.profit.store'), [])->assertForbidden();
        config(['leobot.staking_profit_enabled' => false]);
        $this->expectExceptionMessage('Trading-profit distribution is disabled.');
        StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '10']);
    }

    public function test_audited_same_admin_approval_reconciles_and_missing_or_changed_audit_is_detected(): void
    {
        config(['leobot.admin_profit_verification_enabled' => true]);
        $id = StakingProfitService::submit($this->admin, '100', 'AUDITED-OWNER-RECONCILE-001', [$this->member->id => '100']);
        StakingProfitService::verifyByAdmin($this->admin, $id);
        $report = StakingReconciliationService::inspect();
        $this->assertTrue($report['accounting_passed'], json_encode($report['issues']));
        config(['leobot.admin_profit_verification_enabled' => false]);
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed'], 'Historical approvals do not depend on the current entry switch.');
        $audit = DB::table('member_security_audits')->where('action', 'profit_admin_verified')->first();
        $proof = json_decode($audit->details, true);
        $proof['evidence_digest'] = str_repeat('0', 64);
        DB::table('member_security_audits')->where('id', $audit->id)->update(['details' => json_encode($proof)]);
        $this->assertFalse(StakingReconciliationService::inspect()['accounting_passed']);
        DB::table('member_security_audits')->where('id', $audit->id)->update(['details' => $audit->details]);
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
        DB::table('member_security_audits')->where('id', $audit->id)->delete();
        $this->assertFalse(StakingReconciliationService::inspect()['accounting_passed']);
    }
}
