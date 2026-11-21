<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenerationAllocationService;
use App\Services\IncomeDistributionService;
use App\Services\MagicIncomeReportService;
use App\Services\MemberIncomeReportService;
use App\Services\StakingService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class PeriodIncomeReportingTest extends TestCase
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

    private function stakeAt(string $date, string $key): User
    {
        $this->travelTo(CarbonImmutable::parse($date, 'Asia/Kolkata'));
        config(['leobot.staking_enabled' => true, 'leobot.staking_profit_enabled' => true, 'leobot.production_financial_approval' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        WalletService::credit($member->id, 'available_balance', '100', 'test_fixture', 'fixture:'.$key);
        app(StakingService::class)->create($member->id, '100', $key);

        return $member;
    }

    public function test_new_weekly_profit_counts_only_funded_days_and_does_not_rewrite_on_retry(): void
    {
        $early = $this->stakeAt('2026-11-16 12:00:00', 'period_early_stake_001');
        $late = $this->stakeAt('2026-11-18 00:00:00', 'period_later_stake_001');
        $this->stakeAt('2026-11-21 12:00:00', 'period_after_stake_001');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $ledger = DB::table('financial_ledger')->count();

        $id = IncomeDistributionService::submit($admin, '80', 'PERIOD-DAYS-TEST-001', 'weekly', '2026-11-16', '2026-11-20');

        $this->assertDatabaseHas('staking_profit_allocations', ['statement_id' => $id, 'user_id' => $early->id, 'amount' => '50']);
        $this->assertDatabaseHas('staking_profit_allocations', ['statement_id' => $id, 'user_id' => $late->id, 'amount' => '30']);
        $this->assertDatabaseCount('staking_profit_allocations', 2);
        $this->assertSame($ledger, DB::table('financial_ledger')->count());
        $this->assertSame($id, IncomeDistributionService::submit($admin, '80', 'PERIOD-DAYS-TEST-001', 'weekly', '2026-11-16', '2026-11-20'));
        $this->assertDatabaseCount('staking_profit_statements', 1);
    }

    public function test_period_weights_use_historical_withdrawal_and_refund_dates(): void
    {
        $member = $this->stakeAt('2026-11-16 12:00:00', 'period_hold_stake_001');
        $position = DB::table('staking_positions')->where('user_id', $member->id)->first();
        foreach ([['2026-11-18 04:00:00', '-40', 'hold', '100', '60'], ['2026-11-20 04:00:00', '40', 'refund', '60', '100']] as [$date, $amount, $action, $before, $after]) {
            DB::table('financial_ledger')->insert(['user_id' => $member->id, 'entry_type' => 'staking_principal_'.$action, 'wallet_type' => 'staking_principal', 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after, 'transaction_key' => 'period:'.$action, 'reference_type' => 'staking_position', 'reference_id' => $position->id, 'status' => 'completed', 'created_at' => $date, 'updated_at' => $date]);
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-21', 'Asia/Kolkata'));

        $this->assertSame([$member->id => '420.00000000'], IncomeDistributionService::periodWeights('2026-11-16', '2026-11-20'));
        $member->forceFill(['is_blocked' => true])->save();
        $this->assertSame([], IncomeDistributionService::periodWeights('2026-11-16', '2026-11-20'));
    }

    public function test_daily_and_weekly_income_use_ist_credit_dates_and_only_current_members_income(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        $other = User::factory()->create();
        foreach ([['2026-11-19 18:29:59', '1', 'self_income_settlement'], ['2026-11-19 18:30:00', '2', 'self_income_settlement'], ['2026-11-20 18:29:59', '3', 'magic_income_settlement'], ['2026-11-20 18:29:59', '4', 'generation_income_settlement'], ['2026-11-20 18:30:00', '5', 'magic_income_settlement'], ['2026-11-20 12:00:00', '100', 'verified_deposit']] as $i => [$date, $amount, $type]) {
            $this->travelTo(CarbonImmutable::parse($date, 'UTC'));
            WalletService::credit($member->id, 'available_balance', $amount, $type, 'income-report:'.$i);
        }
        WalletService::credit($other->id, 'available_balance', '1000', 'magic_income_settlement', 'other-income-report');
        $before = DB::table('financial_ledger')->orderBy('id')->get()->toJson();
        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00', 'Asia/Kolkata'));

        $response = $this->actingAs($member)->get(route('member.dashboard', ['income_date' => '2026-11-20', 'user_id' => $other->id]))->assertOk();

        $summary = $response->viewData('paidIncomeSummary');
        $this->assertSame('9.00000000', $summary['daily_usdt']);
        $this->assertSame('15.00000000', $summary['weekly_usdt']);
        $this->assertSame('3.00000000', $response->viewData('magicIncomeSummary')['daily_usdt']);
        $this->assertSame('8.00000000', MagicIncomeReportService::summary($member->id)['lifetime_usdt']);
        $this->assertSame('3.00000000', MemberIncomeReportService::summary($member->id, '2026-11-20', true)['daily_usdt']);
        $response->assertSee('Daily Income')->assertSee('Weekly Income')->assertSee('Income Credit History')->assertDontSee('1000.00000000');
        $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
        $this->get(route('member.dashboard', ['income_date' => 'wrong']))->assertSessionHasErrors('income_date');
    }

    public function test_share_button_credits_once_and_preserves_daily_proration(): void
    {
        $early = $this->stakeAt('2026-11-16 12:00:00', 'button_early_stake_01');
        $late = $this->stakeAt('2026-11-18 12:00:00', 'button_later_stake_01');
        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00', 'Asia/Kolkata'));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        config(['leobot.admin_profit_verification_enabled' => true, 'leobot.self_income_settlement_enabled' => true, 'leobot.generation_income_settlement_enabled' => false]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        $payload = ['amount' => '80', 'period' => 'weekly', 'period_date' => '16/11/2026', 'period_end_date' => '20/11/2026', 'submission_token' => (string) Str::uuid(), 'realized_profit_confirmed' => '1', 'action' => 'share'];

        $this->actingAs($admin)->post(route('admin.income-distribution.submit'), $payload)->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->post(route('admin.income-distribution.submit'), $payload)->assertSessionHasNoErrors();

        $this->assertSame('33.50000000', $early->fresh()->available_balance);
        $this->assertSame('20.10000000', $late->fresh()->available_balance);
        $this->assertDatabaseCount('self_income_settlements', 2);
        $this->assertDatabaseCount('profit_sharing_fundings', 1);
        $this->assertSame('33.50000000', MemberIncomeReportService::summary($early->id)['daily_usdt']);
        $this->assertSame('20.10000000', MemberIncomeReportService::summary($late->id)['weekly_usdt']);
        $this->assertSame('2026-11-16', MemberIncomeReportService::history($early->id)->first()->period_date);
    }

    public function test_disabled_payout_rolls_back_the_complete_share_button_action(): void
    {
        $member = $this->stakeAt('2026-11-18 12:00:00', 'button_rollback_stake1');
        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00', 'Asia/Kolkata'));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        config(['leobot.admin_profit_verification_enabled' => true, 'leobot.self_income_settlement_enabled' => false]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        $before = DB::table('financial_ledger')->orderBy('id')->get()->toJson();

        $this->actingAs($admin)->post(route('admin.income-distribution.submit'), ['amount' => '14', 'period' => 'weekly', 'period_date' => '16/11/2026', 'period_end_date' => '20/11/2026', 'submission_token' => (string) Str::uuid(), 'realized_profit_confirmed' => '1', 'action' => 'share'])->assertSessionHasErrors('distribution');

        $this->assertDatabaseCount('staking_profit_statements', 0);
        $this->assertDatabaseCount('profit_sharing_fundings', 0);
        $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
    }

    public function test_generation_allocation_rejects_an_outer_read_committed_transaction(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        DB::beginTransaction();
        try {
            GenerationAllocationService::allocate($admin, 1);
            $this->fail('A read-committed outer transaction must not weaken the allocation snapshot.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Allocation requires repeatable-read or serializable snapshot isolation.', $error->getMessage());
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('generation_allocation_runs', 0);
    }

    public function test_future_period_cannot_create_profit_or_allocate_pre_activation_days(): void
    {
        $this->stakeAt('2026-11-18 12:00:00', 'period_noearly_stake1');
        $this->assertSame([], IncomeDistributionService::periodWeights('2026-11-16', '2026-11-17'));
        $this->expectExceptionMessage('future periods are not allowed');
        IncomeDistributionService::periodWeights('2026-11-18', '2026-11-20');
    }
}
