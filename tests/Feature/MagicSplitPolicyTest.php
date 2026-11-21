<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncomeDistributionService;
use App\Services\MagicMatrixSettingsService;
use App\Services\MagicSplitPolicy;
use App\Services\StakingProfitService;
use App\Services\StakingReconciliationService;
use App\Services\StakingService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class MagicSplitPolicyTest extends TestCase
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

    private function setupFunding(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00', 'Asia/Kolkata'));
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.staking_profit_enabled' => true, 'leobot.admin_profit_verification_enabled' => true, 'leobot.self_income_settlement_enabled' => true, 'leobot.generation_income_settlement_enabled' => false, 'leobot_magic.magic_matrix_enrollment_enabled' => true, 'leobot_magic.magic_matrix_settlement_enabled' => true, 'leobot_magic.magic_matrix_settings_write_enabled' => true, 'leobot_magic.production_financial_approval' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true, 'settlement_enabled' => true, 'minimum_tier_usdt' => '50', 'earning_cap_multiplier' => 3]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $root = $this->stake();
        $child = $this->stake();
        MagicSplitPolicy::activate($admin);
        $rates = array_fill_keys(range(1, 20), '3');
        $rates[20] = '0';
        MagicMatrixSettingsService::updateLevelRates($admin, $rates);
        $id = IncomeDistributionService::submit($admin, '100', 'MAGIC-SPLIT-'.Str::uuid(), 'weekly', '2026-11-21');
        StakingProfitService::verifyByAdmin($admin, $id);

        return [$admin, $root, $child, $id];
    }

    private function stake(): User
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        WalletService::credit($member->id, 'available_balance', '50', 'test_fixture', 'magic:fixture:'.$member->id);
        app(StakingService::class)->create($member->id, '50', 'magic_split_stake_'.$member->id);

        return $member;
    }

    public function test_65_30_3_2_funding_preserves_snapshots_and_retries_never_duplicate_credits(): void
    {
        [$admin, $root, $child, $id] = $this->setupFunding();
        $funding = DB::table('profit_sharing_fundings')->first();
        $snapshot = $funding->equal_members_snapshot;
        $this->assertSame('65.00000000', (string) $funding->self_amount);
        $this->assertSame('30.00000000', (string) $funding->generation_amount);
        $this->assertSame('5.00000000', (string) $funding->magic_amount);
        $late = $this->stake();
        DB::table('magic_matrix_level_rates')->update(['pool_percent' => '5']);

        IncomeDistributionService::distribute($admin, $id);
        IncomeDistributionService::distribute($admin, $id);

        $this->assertSame('33.54500000', $root->fresh()->available_balance);
        $this->assertSame('33.50000000', $child->fresh()->available_balance);
        $this->assertSame('0.00000000', $late->fresh()->available_balance);
        $this->assertDatabaseCount('magic_equal_entitlements', 2);
        $this->assertDatabaseCount('magic_equal_settlement_runs', 1);
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'magic_balance' => '2.955', 'total_magic_settled' => '2.045', 'total_self_settled' => '65']);
        $this->assertSame($snapshot, DB::table('profit_sharing_fundings')->value('equal_members_snapshot'));
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
    }

    public function test_production_approval_recognizes_the_new_version_without_allowing_self_approval_bypass(): void
    {
        [$admin, $root, $child, $id] = $this->setupFunding();
        $this->app->detectEnvironment(fn () => 'production');
        try {
            IncomeDistributionService::distribute($admin, $id);
            $this->assertSame('33.54500000', $root->fresh()->available_balance);
            $this->assertSame('33.50000000', $child->fresh()->available_balance);
            DB::table('member_security_audits')->where('action', 'profit_admin_verified')->delete();
            try {
                IncomeDistributionService::distribute($admin, $id);
                $this->fail('An altered approval must not authorize payout.');
            } catch (\RuntimeException $error) {
                $this->assertSame('Recorded trading-profit approval is invalid. No payout was made.', $error->getMessage());
            }
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_concurrent_equal_settlement_requests_credit_the_fixed_cohort_once(): void
    {
        [$admin, $root, $child] = $this->setupFunding();
        $fund = (int) DB::table('profit_sharing_fundings')->value('id');
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/magic-equal-worker.php'), (string) $admin->id, (string) $fund], base_path(), timeout: 30);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        }
        $this->assertDatabaseCount('magic_equal_settlement_runs', 1);
        $this->assertDatabaseCount('magic_equal_entitlements', 2);
        $this->assertSame('1.00000000', $root->fresh()->available_balance);
        $this->assertSame('1.00000000', $child->fresh()->available_balance);
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['id' => 1, 'magic_balance' => '3', 'total_magic_settled' => '2']);
    }

    public function test_member_magic_menus_separate_equal_from_matrix_and_show_earning_period(): void
    {
        [$admin, $root, $child, $id] = $this->setupFunding();
        IncomeDistributionService::distribute($admin, $id);
        DB::table('income_distribution_requests')->where('statement_id', $id)->update(['period_date' => '2026-11-16', 'period_end_date' => '2026-11-20']);
        $root->forceFill(['security_pin' => Hash::make('123456')])->save();
        $before = DB::table('financial_ledger')->orderBy('id')->get()->toJson();

        $overview = $this->actingAs($root)->get(route('member.dashboard'))->assertOk();
        $overview->assertSee('id="self-magic-income-total"', false)->assertSee('View Self Magic Income');
        $this->assertSame('1.00000000', $overview->viewData('selfMagicIncomeSummary')['lifetime_usdt']);

        $equal = $this->actingAs($root)->get(route('member.detail', ['panel' => 'self-magic-income', 'user_id' => $child->id]))->assertOk();

        $this->assertSame('1.00000000', $equal->viewData('magicComponentSummary')['lifetime_usdt']);
        $this->assertCount(1, $equal->viewData('magicComponentHistory'));
        $equal->assertSee('Self Magic Income — 2% Equal')->assertSee('16/11/2026 TO 20/11/2026')->assertDontSee('Income totals use the wallet credit date (IST).');
        $matrix = $this->get(route('member.detail', ['panel' => 'magic-income', 'user_id' => $child->id]))->assertOk();
        $this->assertSame('0.04500000', $matrix->viewData('magicComponentSummary')['lifetime_usdt']);
        $this->assertCount(1, $matrix->viewData('magicComponentHistory'));
        $matrix->assertSee('Magic Income — Matrix')->assertSee('16/11/2026 TO 20/11/2026');
        $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
    }

    public function test_equal_share_and_matrix_share_use_one_cap_and_keep_capped_excess_held(): void
    {
        [$admin, $root, $child, $id] = $this->setupFunding();
        $cycle = DB::table('magic_matrix_cycles')->where('user_id', $root->id)->first();
        DB::table('magic_matrix_cycles')->where('id', $cycle->id)->update(['magic_paid_usdt' => '149.9']);

        IncomeDistributionService::distribute($admin, $id);

        $this->assertSame('32.60000000', $root->fresh()->available_balance);
        $this->assertDatabaseHas('magic_equal_entitlements', ['user_id' => $root->id, 'nominal_amount' => '1', 'paid_amount' => '0.055', 'held_amount' => '0.945', 'status' => 'held_cap']);
        $this->assertDatabaseHas('magic_matrix_cycles', ['id' => $cycle->id, 'status' => 'capped', 'magic_paid_usdt' => '150']);
        $this->assertSame('33.50000000', $child->fresh()->available_balance);
    }

    public function test_equal_audit_failure_rolls_back_all_wallet_credits_in_the_payout_attempt(): void
    {
        [$admin, $root, $child, $id] = $this->setupFunding();
        DB::unprepared("CREATE FUNCTION pg_temp.reject_equal_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Equal audit unavailable'; END $$");
        DB::unprepared('CREATE TRIGGER reject_equal_audit BEFORE INSERT ON magic_equal_entitlements FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_equal_audit()');
        try {
            IncomeDistributionService::distribute($admin, $id);
            $this->fail('A failed equal audit must roll back the payout.');
        } catch (QueryException) {
            $this->assertSame('0.00000000', $root->fresh()->available_balance);
            $this->assertSame('0.00000000', $child->fresh()->available_balance);
            $this->assertDatabaseCount('self_income_settlements', 0);
            $this->assertDatabaseCount('magic_equal_settlement_runs', 0);
            $this->assertDatabaseCount('magic_income_entitlements', 0);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_equal_audit ON magic_equal_entitlements');
        }
    }
}
