<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncomeDistributionService;
use App\Services\LegacyIncomeCorrectionService;
use App\Services\MagicSplitPolicy;
use App\Services\MemberIncomeReportService;
use App\Services\StakingProfitService;
use App\Services\StakingReconciliationService;
use App\Services\StakingService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class LegacyIncomeCorrectionTest extends TestCase
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

    private function legacy(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00', 'Asia/Kolkata'));
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.staking_profit_enabled' => true, 'leobot.admin_profit_verification_enabled' => true, 'leobot.self_income_settlement_enabled' => true, 'leobot.generation_income_settlement_enabled' => false, 'leobot_magic.magic_matrix_enrollment_enabled' => true, 'leobot_magic.magic_matrix_settlement_enabled' => true, 'leobot_magic.production_financial_approval' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true, 'self_profit_percent' => '67', 'distribution_policy' => 'unified_67_30_3']);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true, 'trading_profit_pool_percent' => '3']);
        DB::table('generation_pool_settings')->where('id', 1)->update(['generation_enabled' => false, 'trading_profit_pool_percent' => '30']);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true, 'settlement_enabled' => true, 'minimum_tier_usdt' => '50']);
        DB::table('magic_matrix_level_rates')->update(['pool_percent' => '5']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $members = [];
        for ($i = 0; $i < 2; $i++) {
            $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
            WalletService::credit($member->id, 'available_balance', '50', 'fixture', 'correction:fixture:'.$member->id);
            app(StakingService::class)->create($member->id, '50', 'correction_stake_'.$member->id);
            $members[] = $member;
        }
        $id = IncomeDistributionService::submit($admin, '14', 'ORIGINAL-WEEKLY-CORRECTION', 'weekly', '2026-11-21');
        StakingProfitService::verifyByAdmin($admin, $id);
        IncomeDistributionService::distribute($admin, $id);
        $paid = (string) DB::table('financial_ledger')->whereIn('entry_type', ['self_income_settlement', 'magic_income_settlement'])->sum('amount');
        MagicSplitPolicy::activate($admin);

        return [$admin, $members, $id, $paid];
    }

    public function test_correction_preserves_old_journals_and_rebooks_one_profit_with_equal_shares_once(): void
    {
        [$admin,$members,$id,$paid] = $this->legacy();
        $oldLedger = DB::table('financial_ledger')->orderBy('id')->get()->toJson();
        $oldIds = DB::table('financial_ledger')->pluck('id');
        $new = LegacyIncomeCorrectionService::correct($admin, $id, '14', $paid);
        $this->assertSame($new, LegacyIncomeCorrectionService::correct($admin, $id, '14', $paid));
        $this->assertSame($oldLedger, DB::table('financial_ledger')->whereIn('id', $oldIds)->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'reversed']);
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $new, 'status' => 'reserved', 'amount' => '14']);
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'total_self_funded' => '9.1', 'total_self_settled' => '9.1', 'total_magic_funded' => '0.7']);
        $this->assertSame('-14.00000000', (string) DB::table('financial_ledger')->where('wallet_type', 'external_trading_profit')->sum('amount'));
        foreach ($members as $member) {
            $this->assertSame('0.14000000', MemberIncomeReportService::summary($member->id, null, true, 'equal')['lifetime_usdt']);
            $this->assertSame($member->fresh()->available_balance, $member->fresh()->lifetime_income);
        }
        $this->assertDatabaseCount('magic_equal_settlement_runs', 1);
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
        try {
            IncomeDistributionService::distribute($admin, $id);
            $this->fail('Reversed receipt must not pay twice.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Trading statement is not pending.', $e->getMessage());
        }
    }

    public function test_spent_income_prevents_entire_correction_without_partial_debits(): void
    {
        [$admin,$members,$id,$paid] = $this->legacy();
        WalletService::debit($members[1]->id, 'available_balance', '4.69', 'fixture_spent', 'correction:spent');
        $before = DB::table('financial_ledger')->orderBy('id')->get()->toJson();
        try {
            LegacyIncomeCorrectionService::correct($admin, $id, '14', $paid);
            $this->fail('Spent balance accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('insufficient available balance', $e->getMessage());
        }
        $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'reserved']);
    }

    public function test_unauthorized_actor_or_wrong_expected_amount_cannot_reverse(): void
    {
        [$admin,$members,$id,$paid] = $this->legacy();
        $before = DB::table('financial_ledger')->count();
        foreach ([[$members[0], '14', $paid], [$admin, '14', '0']] as [$actor,$amount,$expected]) {
            try {
                LegacyIncomeCorrectionService::correct($actor, $id, $amount, $expected);
                $this->fail('Invalid correction accepted.');
            } catch (\RuntimeException) {
                $this->assertSame($before, DB::table('financial_ledger')->count());
            }
        }
    }

    public function test_failed_replacement_credit_rolls_back_all_reversals_and_reserve_changes(): void
    {
        [$admin,$members,$id,$paid] = $this->legacy();
        $before = DB::table('financial_ledger')->orderBy('id')->get()->toJson();
        $reserves = DB::table('profit_sharing_reserve_accounts')->first();
        DB::unprepared("CREATE FUNCTION pg_temp.reject_correction_equal() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Replacement audit unavailable'; END $$");
        DB::unprepared('CREATE TRIGGER reject_correction_equal BEFORE INSERT ON magic_equal_entitlements FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_correction_equal()');
        try {
            LegacyIncomeCorrectionService::correct($admin, $id, '14', $paid);
            $this->fail('Broken audit accepted.');
        } catch (QueryException) {
            $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
            $this->assertEquals($reserves, DB::table('profit_sharing_reserve_accounts')->first());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_correction_equal ON magic_equal_entitlements');
        }
    }

    public function test_concurrent_corrections_create_only_one_replacement_and_equal_run(): void
    {
        [$admin, $members, $id, $paid] = $this->legacy();
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/legacy-income-correction-worker.php'), (string) $admin->id, (string) $id, $paid], base_path(), timeout: 45);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        }
        $this->assertDatabaseCount('magic_equal_settlement_runs', 1);
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'legacy_income_corrected')->count());
        $this->assertSame('-14.00000000', (string) DB::table('financial_ledger')->where('wallet_type', 'external_trading_profit')->sum('amount'));
    }

    public function test_missing_reversal_proof_fails_reconciliation_and_disabled_controls_prevent_correction(): void
    {
        [$admin, $members, $id, $paid] = $this->legacy();
        config(['leobot_magic.magic_matrix_settlement_enabled' => false]);
        $count = DB::table('financial_ledger')->count();
        try {
            LegacyIncomeCorrectionService::correct($admin, $id, '14', $paid);
            $this->fail('Disabled control accepted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('settlement controls', $error->getMessage());
        }
        $this->assertSame($count, DB::table('financial_ledger')->count());
        config(['leobot_magic.magic_matrix_settlement_enabled' => true]);
        LegacyIncomeCorrectionService::correct($admin, $id, '14', $paid);
        DB::table('member_security_audits')->where('action', 'legacy_income_corrected')->delete();
        $this->assertFalse(StakingReconciliationService::inspect()['accounting_passed']);
    }
}
