<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenerationIncomeSettlementService;
use App\Services\MagicIncomeSettlementService;
use App\Services\MagicMatrixCycleService;
use App\Services\SelfIncomeSettlementService;
use App\Services\UnifiedProfitSharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FundedIncomeSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function reserve(): array
    {
        config(['leobot.self_income_settlement_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        DB::table('company_wallet')->where('id', 1)->update(['balance' => '100']);
        $funding = UnifiedProfitSharingService::fund($admin, '100', 'FUNDED-SETTLEMENT-001', [$member->id => '100']);
        $reserve = (int) DB::table('profit_sharing_self_reserves')->where('funding_id', $funding)->value('id');

        return [$admin, $member, $reserve, $funding];
    }

    public function test_self_payout_has_equal_reserve_debit_and_member_credit_and_retry_is_idempotent(): void
    {
        [$admin, $member, $reserve] = $this->reserve();
        $id = SelfIncomeSettlementService::settle($admin, $reserve);
        $this->assertSame($id, SelfIncomeSettlementService::settle($admin, $reserve));
        $this->assertSame('67.00000000', $member->fresh()->available_balance);
        $entries = DB::table('financial_ledger')->where('reference_type', 'profit_sharing_self_reserve')->where('reference_id', $reserve);
        $this->assertSame(2, $entries->count());
        $this->assertSame('0.00000000', (string) $entries->sum('amount'));
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'self_balance' => '0.00000000', 'total_self_settled' => '67.00000000']);
    }

    public function test_deleted_recipient_cannot_receive_reserved_income(): void
    {
        [$admin, $member, $reserve] = $this->reserve();
        DB::table('users')->where('id', $member->id)->update(['deleted_at' => now()]);
        try {
            SelfIncomeSettlementService::settle($admin, $reserve);
            $this->fail('Deleted recipient accepted.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Member not eligible at settlement.', $error->getMessage());
        }
        $this->assertDatabaseCount('self_income_settlements', 0);
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'self_balance' => '67.00000000']);
    }

    public function test_wallet_overflow_rolls_back_reserve_and_all_payout_journals(): void
    {
        [$admin, $member, $reserve] = $this->reserve();
        DB::table('users')->where('id', $member->id)->update(['available_balance' => '999999999999.99999999']);
        try {
            SelfIncomeSettlementService::settle($admin, $reserve);
            $this->fail('Overflow accepted.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('self_income_settlements', 0);
            $this->assertSame(0, DB::table('financial_ledger')->where('reference_type', 'profit_sharing_self_reserve')->count());
            $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'self_balance' => '67.00000000']);
        }
    }

    public function test_magic_payout_balances_reserve_and_keeps_missing_ancestor_shares_held(): void
    {
        [$admin, $source, $reserveId, $funding] = $this->reserve();
        $root = User::factory()->create(['role' => 'member', 'status' => 'active']);
        config(['leobot_magic.magic_matrix_enrollment_enabled' => true, 'leobot_magic.magic_matrix_settlement_enabled' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true, 'settlement_enabled' => true, 'minimum_tier_usdt' => '49']);
        DB::table('magic_matrix_level_rates')->update(['pool_percent' => '5']);
        foreach ([$root, $source] as $person) {
            $package = DB::table('activation_packages')->insertGetId(['user_id' => $person->id, 'activation_key' => 'AUDIT-MAG-'.$person->id,
                'investment_amount' => '50', 'status' => 'active', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            MagicMatrixCycleService::enrollFromActivePackage($person, $package, 'AUDIT-ENROLL-'.$person->id);
        }
        $result = MagicIncomeSettlementService::settleSource($admin, $funding, $source->id);
        $this->assertSame('0.15000000', $result['credited_usdt']);
        $this->assertSame('2.85000000', $result['held_usdt']);
        $this->assertSame('0.15000000', $root->fresh()->available_balance);
        $this->assertSame('0.00000000', $source->fresh()->available_balance);
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'magic_balance' => '2.85000000', 'total_magic_settled' => '0.15000000']);
        $entries = DB::table('financial_ledger')->whereIn('transaction_key', ['magic:unified:'.$funding.':'.$source->id.':1', 'magic:reserve:'.$funding.':'.$source->id]);
        $this->assertSame(2, $entries->count());
        $this->assertSame('0.00000000', (string) $entries->sum('amount'));
        $this->assertTrue(MagicIncomeSettlementService::settleSource($admin, $funding, $source->id)['already_settled']);
        $this->assertDatabaseCount('magic_income_entitlements', 20);
    }

    public function test_payable_generation_payout_balances_reserve_and_retries_once(): void
    {
        [$admin, $member, $selfReserve, $funding] = $this->reserve();
        config(['leobot.generation_income_settlement_enabled' => true]);
        $genId = (int) DB::table('profit_sharing_fundings')->where('id', $funding)->value('generation_funding_id');
        $budget = DB::table('generation_level_budgets')->where('funding_id', $genId)->where('level_number', 1)->first();
        $run = DB::table('generation_allocation_runs')->insertGetId(['funding_id' => $genId, 'approved_by' => $admin->id, 'snapshot_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $allocation = DB::table('generation_member_allocations')->insertGetId([
            'allocation_run_id' => $run, 'funding_id' => $genId, 'level_budget_id' => $budget->id, 'user_id' => $member->id,
            'level_number' => 1, 'network_business_snapshot' => '100', 'share_amount' => '9', 'unlocked_through_level_snapshot' => 1,
            'classification' => 'payable', 'qualification_reason' => 'qualified', 'status' => 'qualified_reserved_no_payout', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('generation_pool_accounts')->where('id', 1)->update(['unallocated_balance' => '21', 'payable_balance' => '9']);
        DB::table('generation_level_budgets')->where('id', $budget->id)->update(['unallocated_amount' => '0', 'payable_amount' => '9']);
        $id = GenerationIncomeSettlementService::settle($admin, $allocation);
        $this->assertSame($id, GenerationIncomeSettlementService::settle($admin, $allocation));
        $this->assertSame('9.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseHas('generation_pool_accounts', ['id' => 1, 'balance' => '21.00000000', 'total_paid' => '9.00000000']);
        $entries = DB::table('financial_ledger')->where('reference_type', 'generation_member_allocation')->where('reference_id', $allocation);
        $this->assertSame(2, $entries->count());
        $this->assertSame('0.00000000', (string) $entries->sum('amount'));
    }
}
