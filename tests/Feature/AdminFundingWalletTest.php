<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\AdminFundingWalletService as Funding;
use App\Services\IncomeDistributionService;
use App\Services\MagicSplitPolicy;
use App\Services\MemberIncomeReportService;
use App\Services\StakingPrincipalService;
use App\Services\StakingProfitService;
use App\Services\StakingReconciliationService;
use App\Services\VerifiedDepositService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class AdminFundingWalletTest extends TestCase
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

    private function fixture(): array
    {
        config(['leobot.production_financial_approval' => true, 'leobot.admin_member_activation_enabled' => true, 'leobot.staking_enabled' => true, 'leobot.withdrawals_enabled' => true, 'leobot.staking_profit_enabled' => true, 'leobot.admin_profit_verification_enabled' => true, 'leobot.self_income_settlement_enabled' => true, 'leobot.generation_income_settlement_enabled' => true, 'leobot_magic.magic_matrix_enrollment_enabled' => true, 'leobot_magic.magic_matrix_settlement_enabled' => true, 'leobot_magic.production_financial_approval' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('withdrawal_settings')->where('id', 1)->update(['member_requests_enabled' => true, 'admin_review_enabled' => true, 'principal_release_enabled' => true, 'principal_fee_configured_at' => now(), 'principal_withdrawal_fee_percent' => '5', 'minimum_withdrawal_usdt' => '1']);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        DB::table('generation_pool_settings')->where('id', 1)->update(['generation_enabled' => false]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true, 'settlement_enabled' => true, 'minimum_tier_usdt' => '50', 'earning_cap_multiplier' => 3]);
        DB::table('magic_matrix_level_rates')->update(['pool_percent' => '0']);
        DB::table('magic_matrix_level_rates')->where('level', 1)->update(['pool_percent' => '20']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'security_pin' => Hash::make('123456')]);
        MagicSplitPolicy::activate($admin);

        return [$admin, $member];
    }

    public function test_company_capital_and_activation_are_balanced_and_idempotent_without_fake_income_or_deposits(): void
    {
        [$admin,$member] = $this->fixture();
        $key = (string) Str::uuid();
        $receipt = Funding::topUp($admin, '500', 'COMPANY-RECEIPT-001', true);
        $this->assertSame($receipt, Funding::topUp($admin, '500', 'company-receipt-001', true));
        $position = Funding::activate($admin, $member->id, '200', $key);
        $this->assertSame($position, Funding::activate($admin, $member->id, '200', $key));
        $this->assertSame('300.00000000', Funding::assertReconciled());
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame('member', $member->fresh()->role);
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
        $this->assertSame('0.00000000', $member->fresh()->lifetime_income);
        $this->assertDatabaseCount('staking_positions', 1);
        $this->assertDatabaseCount('activation_packages', 0);
        $this->assertDatabaseCount('member_deposit_requests', 0);
        $this->assertSame('0.00000000', MemberIncomeReportService::summary($member->id)['lifetime_usdt']);
        $this->assertSame('0.00000000', (string) DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
        $weights = IncomeDistributionService::periodWeights(now('Asia/Kolkata')->toDateString(), now('Asia/Kolkata')->toDateString());
        $this->assertSame('200.00000000', $weights[$member->id]);
        $this->assertSame([], IncomeDistributionService::periodWeights(now('Asia/Kolkata')->subDays(2)->toDateString(), now('Asia/Kolkata')->subDay()->toDateString()));
    }

    public function test_funded_principal_gets_approved_self_generation_matrix_and_equal_income_and_can_request_principal(): void
    {
        [$admin,$root] = $this->fixture();
        $child = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'sponsor_user_id' => $root->id, 'sponsor_id' => $root->username]);
        Funding::topUp($admin, '500', 'COMPANY-INCOME-001', true);
        Funding::activate($admin, $root->id, '100', (string) Str::uuid());
        $position = Funding::activate($admin, $child->id, '200', (string) Str::uuid());
        $id = IncomeDistributionService::submit($admin, '90', 'REALIZED-ADMIN-CAPITAL-PROFIT', 'weekly', now('Asia/Kolkata')->toDateString());
        StakingProfitService::verifyByAdmin($admin, $id);
        IncomeDistributionService::distribute($admin, $id);
        $this->assertSame('19.50000000', (string) DB::table('financial_ledger')->where('user_id', $root->id)->where('entry_type', 'self_income_settlement')->sum('amount'));
        $this->assertSame('8.10000000', (string) DB::table('financial_ledger')->where('user_id', $root->id)->where('entry_type', 'generation_income_settlement')->sum('amount'));
        $this->assertSame('0.90000000', MemberIncomeReportService::summary($root->id, null, true, 'equal')['lifetime_usdt']);
        $this->assertSame('0.36000000', MemberIncomeReportService::summary($root->id, null, true, 'matrix')['lifetime_usdt']);
        $wallet = DB::table('usdt_member_wallets')->insertGetId(['user_id' => $child->id, 'network' => 'BEP20', 'address' => '0x'.str_repeat('1', 40), 'verified_at' => now(), 'verification_method' => 'test_fixture', 'proof_digest' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $request = StakingPrincipalService::request($child->fresh(), $position, '10', 'admin_funded_principal_request', $wallet, '5');
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $request, 'gross_amount' => '10', 'net_amount' => '9.5', 'wallet_type' => 'staking_principal']);
        $this->assertSame('200.00000000', Funding::assertReconciled());
        $this->assertTrue(StakingReconciliationService::inspect()['accounting_passed']);
    }

    public function test_insufficient_funding_and_blocked_members_do_not_change_wallets_or_status(): void
    {
        [$admin,$member] = $this->fixture();
        try {
            Funding::activate($admin, $member->id, '200', (string) Str::uuid());
            $this->fail('Unfunded activation accepted.');
        } catch (\RuntimeException$error) {
            $this->assertStringContainsString('Insufficient Admin Funding Wallet', $error->getMessage());
        }
        $this->assertSame('inactive', $member->fresh()->status);
        $this->assertDatabaseCount('financial_ledger', 0);
        Funding::topUp($admin, '200', 'COMPANY-BLOCKED-001', true);
        $member->forceFill(['is_blocked' => true])->save();
        $before = DB::table('financial_ledger')->count();
        try {
            Funding::activate($admin, $member->id, '200', (string) Str::uuid());
            $this->fail('Blocked member accepted.');
        } catch (\RuntimeException) {
            $this->assertSame($before, DB::table('financial_ledger')->count());
        }
        $this->assertSame('200.00000000', Funding::balance());
    }

    public function test_receipt_conflicts_unconfirmed_funds_and_member_access_are_rejected(): void
    {
        [$admin,$member] = $this->fixture();
        foreach ([false, true] as $confirmed) {
            try {
                if (! $confirmed) {
                    Funding::topUp($admin, '200', 'COMPANY-SOURCE-001', false);
                } else {
                    Funding::topUp($member, '200', 'COMPANY-SOURCE-001', true);
                }$this->fail('Unconfirmed or unauthorized funding accepted.');
            } catch (\RuntimeException|AuthorizationException) {
                $this->assertDatabaseCount('financial_ledger', 0);
            }
        }
        Funding::topUp($admin, '200', 'COMPANY-SOURCE-001', true);
        try {
            Funding::topUp($admin, '300', 'COMPANY-SOURCE-001', true);
            $this->fail('Duplicate source amount changed.');
        } catch (\RuntimeException) {
            $this->assertSame('200.00000000', Funding::balance());
        }
        $this->actingAs($member)->post(route('admin.funding-wallet.topup'), ['amount' => '200', 'source_reference' => 'NO-ACCESS-001', 'funds_confirmed' => 1])->assertForbidden();
        $this->post(route('admin.funding-wallet.activate'), ['member_id' => $member->id, 'funded_amount' => '50', 'funded_activation_key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_disabled_staking_and_failed_stake_insert_roll_back_every_capital_and_member_change(): void
    {
        [$admin,$member] = $this->fixture();
        Funding::topUp($admin, '200', 'COMPANY-ROLLBACK-001', true);
        $before = DB::table('financial_ledger')->orderBy('id')->get()->toJson();
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => false]);
        try {
            Funding::activate($admin, $member->id, '200', (string) Str::uuid());
            $this->fail('Disabled staking accepted.');
        } catch (\RuntimeException) {
            $this->assertSame('inactive', $member->fresh()->status);
            $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
        }
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::unprepared("CREATE FUNCTION pg_temp.reject_admin_funded_stake() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Stake audit unavailable'; END $$");
        DB::unprepared('CREATE TRIGGER reject_admin_funded_stake BEFORE INSERT ON staking_positions FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_admin_funded_stake()');
        try {
            Funding::activate($admin, $member->id, '200', (string) Str::uuid());
            $this->fail('Partial funding kept.');
        } catch (QueryException) {
            $this->assertSame('inactive', $member->fresh()->status);
            $this->assertSame('0.00000000', $member->fresh()->available_balance);
            $this->assertSame('200.00000000', Funding::assertReconciled());
            $this->assertSame($before, DB::table('financial_ledger')->orderBy('id')->get()->toJson());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_admin_funded_stake ON staking_positions');
        }
    }

    public function test_admin_forms_save_real_funding_and_create_funded_activation_without_other_wallet_writes(): void
    {
        [$admin,$member] = $this->fixture();
        $this->actingAs($admin)->get(route('admin.funding-wallet'))->assertOk()->assertSee('Add Company Funds');
        $this->post(route('admin.funding-wallet.topup'), ['amount' => '200', 'source_reference' => 'COMPANY-HTTP-001', 'funds_confirmed' => 1])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->get(route('admin.member-access', ['member' => $member->username]))->assertOk()->assertSee('Fund &amp; Activate', false)->assertSee('200.00000000');
        $this->post(route('admin.funding-wallet.activate'), ['member_id' => $member->id, 'funded_amount' => '200', 'funded_activation_key' => (string) Str::uuid()])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame('0.00000000', Funding::balance());
        $this->assertSame('active', $member->fresh()->status);
        $this->get(route('admin.member-access'))->assertOk()->assertSee($member->username);
    }

    public function test_parallel_same_activation_key_is_debited_once_and_distinct_requests_cannot_overdraw(): void
    {
        [$admin, $member] = $this->fixture();
        Funding::topUp($admin, '300', 'COMPANY-CONCURRENT-001', true);
        $key = (string) Str::uuid();
        $results = $this->workers([['activate', (string) $admin->id, (string) $member->id, '200', $key], ['activate', (string) $admin->id, (string) $member->id, '200', $key]]);
        foreach ($results as $process) {
            $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        }
        $this->assertSame('100.00000000', Funding::assertReconciled());
        $this->assertDatabaseCount('staking_positions', 1);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $otherMember = User::factory()->create(['role' => 'member', 'status' => 'inactive']);
        Funding::topUp($admin, '200', 'COMPANY-CONCURRENT-002', true);
        $results = $this->workers([['activate', (string) $admin->id, (string) $member->id, '200', (string) Str::uuid()], ['activate', (string) $otherAdmin->id, (string) $otherMember->id, '200', (string) Str::uuid()]]);
        $codes = array_map(fn ($process) => $process->getExitCode(), $results);
        sort($codes);
        $this->assertSame([0, 2], $codes, implode('', array_map(fn ($process) => $process->getOutput().$process->getErrorOutput(), $results)));
        $this->assertSame('100.00000000', Funding::assertReconciled());
        $this->assertDatabaseCount('staking_positions', 2);
    }

    public function test_parallel_receipt_retries_record_capital_once(): void
    {
        [$admin] = $this->fixture();
        $results = $this->workers([['topup', (string) $admin->id, '200', 'COMPANY-DUPLICATE-001'], ['topup', (string) $admin->id, '200', 'COMPANY-DUPLICATE-001']]);
        foreach ($results as $process) {
            $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        }
        $this->assertSame('200.00000000', Funding::assertReconciled());
        $this->assertSame(1, DB::table('financial_ledger')->where('entry_type', 'admin_capital_received')->count());
    }

    private function workers(array $arguments): array
    {
        $processes = [];
        foreach ($arguments as $args) {
            $process = new Process(array_merge([PHP_BINARY, base_path('tests/Support/admin-funding-worker.php')], $args), base_path(), timeout: 45);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
        }

        return $processes;
    }

    public function test_missing_source_entry_rolls_back_topup_and_corruption_fails_reconciliation(): void
    {
        [$admin] = $this->fixture();
        DB::unprepared("CREATE FUNCTION pg_temp.reject_admin_capital_source() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.wallet_type='external_company_capital' THEN RAISE EXCEPTION 'Source unavailable'; END IF; RETURN NEW; END $$");
        DB::unprepared('CREATE TRIGGER reject_admin_capital_source BEFORE INSERT ON financial_ledger FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_admin_capital_source()');
        try {
            Funding::topUp($admin, '200', 'COMPANY-SOURCE-ROLLBACK', true);
            $this->fail('Partial receipt kept.');
        } catch (QueryException) {
            $this->assertSame('0.00000000', Funding::balance());
            $this->assertDatabaseCount('financial_ledger', 0);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_admin_capital_source ON financial_ledger');
        }
        Funding::topUp($admin, '200', 'COMPANY-SOURCE-ROLLBACK', true);
        DB::table('financial_ledger')->where('wallet_type', 'external_company_capital')->delete();
        $this->assertFalse(StakingReconciliationService::inspect()['accounting_passed']);
    }

    public function test_invalid_amounts_and_disabled_financial_approval_cannot_add_capital(): void
    {
        [$admin] = $this->fixture();
        foreach (['0', '-1', '1e3', '1.000000001', '1000000000000'] as $amount) {
            try {
                Funding::topUp($admin, $amount, 'COMPANY-INVALID-001', true);
                $this->fail('Invalid money accepted.');
            } catch (\RuntimeException) {
                $this->assertDatabaseCount('financial_ledger', 0);
            }
        }
        config(['leobot.production_financial_approval' => false]);
        $this->expectExceptionMessage('Company funding or admin activation approval is OFF.');
        Funding::topUp($admin, '200', 'COMPANY-INVALID-001', true);
    }

    public function test_known_member_deposit_and_capital_receipts_cannot_be_counted_twice(): void
    {
        [$admin, $member] = $this->fixture();
        $hash = '0x'.str_repeat('d', 64);
        $deposit = DB::table('member_deposit_requests')->insertGetId(['user_id' => $member->id, 'request_key' => 'capital_source_conflict', 'network' => 'BEP20', 'tx_hash' => $hash, 'amount' => '200', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        try {
            Funding::topUp($admin, '200', $hash, true);
            $this->fail('Member deposit used as capital.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('already assigned', $error->getMessage());
        }
        $this->assertSame('0.00000000', Funding::balance());
        DB::table('member_deposit_requests')->where('id', $deposit)->update(['status' => 'rejected']);
        Funding::topUp($admin, '200', $hash, true);
        $this->expectExceptionMessage('already recorded as company capital');
        Funding::assertSourceNotCapital($hash);
    }

    public function test_capital_transaction_cannot_be_approved_again_as_member_deposit_or_trading_profit(): void
    {
        [$admin, $member] = $this->fixture();
        $hash = '0x'.str_repeat('e', 64);
        Funding::topUp($admin, '200', $hash, true);
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        config(['leobot.deposits_enabled' => true, 'leobot_deposits.receiving_addresses.BEP20' => '0x'.str_repeat('2', 40)]);
        $deposit = VerifiedDepositService::submit($member->id, '200', 'BEP20', $hash, 'capital_forward_conflict');
        try {
            VerifiedDepositService::approve($admin->id, $deposit);
            $this->fail('Company capital credited as member deposit.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('already recorded as company capital', $error->getMessage());
        }
        $this->assertSame('0.00000000', $member->fresh()->activation_balance);
        $this->assertDatabaseHas('member_deposit_requests', ['id' => $deposit, 'status' => 'pending']);
        Funding::activate($admin, $member->id, '50', (string) Str::uuid());
        $statement = IncomeDistributionService::submit($admin, '90', $hash, 'weekly', now('Asia/Kolkata')->toDateString());
        try {
            StakingProfitService::verifyByAdmin($admin, $statement);
            $this->fail('Company capital rebooked as trading profit.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('already recorded as company capital', $error->getMessage());
        }
        $this->assertSame('150.00000000', Funding::assertReconciled());
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $statement, 'status' => 'pending_verification']);
    }
}
