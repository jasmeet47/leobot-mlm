<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingReconciliationService;
use App\Support\DisposableDatabase;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class FlexibleStakingMigrationTest extends TestCase
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

    public function test_forward_upgrade_preserves_legacy_balances_positions_deposits_fees_and_switches(): void
    {
        $migration = require database_path('migrations/2026_11_04_084523_add_flexible_staking_accounting.php');
        $migration->down();
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'available_balance' => '50', 'activation_balance' => '25']);
        $position = DB::table('staking_positions')->insertGetId(['user_id' => $member->id, 'amount' => '50',
            'request_key' => 'legacy_stake_0001', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $ledger = DB::table('financial_ledger')->insertGetId(['user_id' => $member->id, 'entry_type' => 'staking_principal',
            'wallet_type' => 'available_balance', 'amount' => '-50', 'balance_before' => '100', 'balance_after' => '50',
            'transaction_key' => 'staking:legacy_stake_0001', 'reference_type' => 'staking_position', 'reference_id' => $position,
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $deposit = DB::table('member_deposit_requests')->insertGetId(['user_id' => $member->id, 'request_key' => 'legacy_deposit_001',
            'amount' => '25', 'network' => 'BEP20', 'tx_hash' => '0x'.str_repeat('a', 64), 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $before = (array) DB::table('users')->find($member->id);
        $oldFee = DB::table('withdrawal_settings')->where('id', 1)->value('principal_withdrawal_fee_percent');
        $migration->up();
        (require database_path('migrations/2026_11_05_012651_link_staking_profit_to_unified_funding.php'))->up();
        $this->assertSame($before, (array) DB::table('users')->find($member->id));
        $this->assertSame($oldFee, DB::table('withdrawal_settings')->where('id', 1)->value('principal_withdrawal_fee_percent'));
        $this->assertDatabaseHas('staking_positions', ['id' => $position, 'amount' => '50.00000000', 'withdrawn_amount' => '0.00000000', 'funding_wallet' => 'available_balance']);
        $this->assertDatabaseHas('member_deposit_requests', ['id' => $deposit, 'status' => 'approved', 'receiving_address' => null]);
        $this->assertDatabaseHas('financial_ledger', ['id' => $ledger, 'amount' => '-50.00000000']);
        $this->assertDatabaseCount('financial_ledger', 1);
        $this->assertDatabaseHas('staking_controls', ['id' => 1, 'enabled' => false]);
        $this->assertNull(DB::table('withdrawal_settings')->where('id', 1)->value('principal_fee_configured_at'));
        $this->assertFalse(StakingReconciliationService::inspect()['accounting_passed'], 'Legacy journal gaps must be reported rather than silently credited.');
    }
}
