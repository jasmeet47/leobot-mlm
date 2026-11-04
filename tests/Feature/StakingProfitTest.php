<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingProfitService;
use App\Services\StakingService;
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
        $this->assertSame('7.12345678', $this->member->fresh()->available_balance);
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
        $other->forceFill(['available_balance' => '999999999999.99999999'])->save();
        $id = StakingProfitService::submit($this->admin, '10', 'TRADING-RECEIPT-001', [$this->member->id => '5', $other->id => '5']);
        try {
            StakingProfitService::approve($this->reviewer, $id);
            $this->fail('Overflow accepted');
        } catch (\RuntimeException) {
            $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
            $this->assertSame('999999999999.99999999', $other->fresh()->available_balance);
            $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'pending_verification', 'verified_by' => null]);
            $this->assertSame(0, DB::table('financial_ledger')->where('reference_type', 'staking_profit_statement')->count());
        }
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
}
