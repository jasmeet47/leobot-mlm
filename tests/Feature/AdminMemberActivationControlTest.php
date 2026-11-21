<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\MemberAccountAdministration;
use App\Services\MemberWithdrawalControl;
use App\Services\StakingPrincipalService;
use App\Services\StakingService;
use App\Services\WalletService;
use App\Services\WithdrawalEmailOtpService;
use App\Services\WithdrawalPaymentConfirmationService;
use App\Services\WithdrawalRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialSeedState;
use Tests\TestCase;

final class AdminMemberActivationControlTest extends TestCase
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
        config(['leobot.production_financial_approval' => true, 'leobot.withdrawals_enabled' => true, 'leobot.staking_enabled' => true, 'leobot_withdrawal.external_payment_verification_enabled' => true]);
        DB::table('withdrawal_settings')->where('id', 1)->update(['member_requests_enabled' => true, 'admin_review_enabled' => true, 'principal_release_enabled' => true, 'principal_fee_configured_at' => now(), 'principal_withdrawal_fee_percent' => '5', 'minimum_withdrawal_usdt' => '1']);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        WalletService::credit($member->id, 'available_balance', '100', 'fixture', 'member-control:'.$member->id);
        $position = app(StakingService::class)->create($member->id, '50', 'member_control_stake_'.$member->id);
        $wallet = DB::table('usdt_member_wallets')->insertGetId(['user_id' => $member->id, 'network' => 'BEP20', 'address' => '0x'.str_repeat('1', 40), 'verified_at' => now(), 'verification_method' => 'test_fixture', 'proof_digest' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);

        return [$admin, $member, $position, $wallet];
    }

    public function test_one_click_account_activation_needs_no_deposit_and_is_audited_without_financial_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'inactive']);
        $this->actingAs($admin)->get(route('admin.member-access', ['member' => $member->username]))->assertOk()->assertSee('Activate Account');
        $data = ['member_id' => $member->id, 'expected_status' => 'inactive'];
        $this->post(route('admin.member-access.activate-account'), $data)->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->post(route('admin.member-access.activate-account'), $data)->assertSessionHasNoErrors();
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame('member', $member->fresh()->role);
        $this->assertSame('0.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('financial_ledger', 0);
        $this->assertDatabaseCount('activation_packages', 0);
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'admin_member_account_activation')->count());
        $untracked = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $page = $this->get(route('admin.member-access'))->assertOk()->assertSee('Admin Activated Members')->assertSee($member->username);
        $page->assertDontSee($untracked->username);
        $page->assertSee(route('admin.member-access.withdrawals'), false);
    }

    public function test_activation_and_switches_require_admin_and_cannot_activate_blocked_or_admin_targets(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'inactive']);
        $this->actingAs($member)->post(route('admin.member-access.activate-account'), ['member_id' => $member->id, 'expected_status' => 'inactive'])->assertForbidden();
        $this->post(route('admin.member-access.withdrawals'), ['member_id' => $member->id, 'enabled' => 0, 'expected_enabled' => 1])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.member-access.activate-account'), ['member_id' => $admin->id, 'expected_status' => 'active'])->assertSessionHasErrors();
        $member->forceFill(['is_blocked' => true])->save();
        $this->post(route('admin.member-access.activate-account'), ['member_id' => $member->id, 'expected_status' => 'inactive'])->assertSessionHasErrors();
        $this->assertSame('inactive', $member->fresh()->status);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_off_blocks_earnings_principal_and_otp_without_debit_and_on_restores_requests(): void
    {
        [$admin,$member,$position,$wallet] = $this->fixture();
        Mail::fake();
        $this->actingAs($admin)->post(route('admin.member-access.withdrawals'), ['member_id' => $member->id, 'enabled' => 0, 'expected_enabled' => 1])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertFalse(MemberWithdrawalControl::enabled($member->id));
        $before = DB::table('financial_ledger')->count();
        foreach (['earnings', 'principal', 'otp'] as $kind) {
            try {
                if ($kind === 'earnings') {
                    WithdrawalRequestService::request($member, '1', 'control_earnings_off', 'earnings', $wallet);
                } elseif ($kind === 'principal') {
                    StakingPrincipalService::request($member, $position, '1', 'control_principal_off', $wallet, '5');
                } else {
                    WithdrawalEmailOtpService::issue($member, ['amount' => '1', 'request_key' => 'control_otp_off', 'usdt_wallet_id' => $wallet]);
                }
                $this->fail('OFF control bypassed.');
            } catch (\RuntimeException$error) {
                $this->assertStringContainsString('Withdrawals are OFF', $error->getMessage());
            }
        }
        Mail::assertNothingSent();
        $this->assertSame($before, DB::table('financial_ledger')->count());
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->actingAs($member)->get(route('member.withdrawals.index'))->assertOk()->assertViewHas('enabled', false);
        MemberWithdrawalControl::set($admin, $member->id, true, false);
        WithdrawalRequestService::request($member, '1', 'control_earnings_on', 'earnings', $wallet);
        StakingPrincipalService::request($member, $position, '1', 'control_principal_on', $wallet, '5');
        $this->assertDatabaseCount('withdrawal_requests', 2);
    }

    public function test_off_blocks_approval_and_new_payment_but_rejection_can_refund(): void
    {
        [$admin,$member,$position,$wallet] = $this->fixture();
        $request = WithdrawalRequestService::request($member, '1', 'control_hold', 'earnings', $wallet);
        MemberWithdrawalControl::set($admin, $member->id, false, true);
        try {
            WithdrawalRequestService::approve($admin, $request);
            $this->fail('OFF approval accepted.');
        } catch (\RuntimeException$error) {
            $this->assertStringContainsString('Withdrawals are OFF', $error->getMessage());
        }
        WithdrawalRequestService::reject($admin, $request);
        $this->assertSame('50.00000000', $member->fresh()->available_balance);
        MemberWithdrawalControl::set($admin, $member->id, true, false);
        $request = WithdrawalRequestService::request($member, '1', 'control_payment', 'earnings', $wallet);
        WithdrawalRequestService::approve($admin, $request);
        MemberWithdrawalControl::set($admin, $member->id, false, true);
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $service = new WithdrawalPaymentConfirmationService($chain);
        try {
            $service->beginPayment($admin, $request);
            $this->fail('OFF payment initiation accepted.');
        } catch (\RuntimeException$error) {
            $this->assertStringContainsString('Withdrawals are OFF', $error->getMessage());
        }
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $request, 'status' => 'approved_awaiting_payment']);
        MemberWithdrawalControl::set($admin, $member->id, true, false);
        $service->beginPayment($admin, $request);
        MemberWithdrawalControl::set($admin, $member->id, false, true);
        $hash = '0x'.str_repeat('b', 64);
        $service->submitPayment($admin, $request, 'BEP20', $hash);
        $service->confirm($admin, $request, 'BEP20', $hash);
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $request, 'status' => 'paid']);
    }

    public function test_switch_is_per_member_and_audit_failure_rolls_back_activation_and_control(): void
    {
        [$admin,$member] = $this->fixture();
        $other = User::factory()->create(['role' => 'member', 'status' => 'inactive']);
        MemberWithdrawalControl::set($admin, $member->id, false, true);
        MemberWithdrawalControl::set($admin, $member->id, false, true);
        $this->assertTrue(MemberWithdrawalControl::enabled($other->id));
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'admin_member_withdrawals')->count());
        DB::unprepared("CREATE FUNCTION pg_temp.reject_member_controls() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit unavailable'; END $$");
        DB::unprepared('CREATE TRIGGER reject_member_controls BEFORE INSERT ON member_security_audits FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_member_controls()');
        try {
            try {
                MemberAccountAdministration::activateAccount($admin, $other->id, 'inactive');
                $this->fail('Missing audit accepted.');
            } catch (QueryException) {
                $this->assertSame('inactive', $other->fresh()->status);
            }
            try {
                MemberWithdrawalControl::set($admin, $member->id, true, false);
                $this->fail('Missing control audit accepted.');
            } catch (QueryException) {
                $this->assertFalse(MemberWithdrawalControl::enabled($member->id));
            }
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_member_controls ON member_security_audits');
        }
    }

    public function test_withdrawal_waits_for_concurrent_off_control_and_cannot_debit(): void
    {
        [$admin, $member, $position, $wallet] = $this->fixture();
        $before = DB::table('financial_ledger')->count();
        $process = new Process([PHP_BINARY, base_path('tests/Support/member-withdrawal-off-worker.php'), (string) $admin->id, (string) $member->id], base_path(), timeout: 15);
        $process->start();
        $deadline = microtime(true) + 5;
        while (! str_contains($process->getOutput(), 'OFF_LOCKED') && $process->isRunning() && microtime(true) < $deadline) {
            usleep(10000);
        }
        $this->assertStringContainsString('OFF_LOCKED', $process->getOutput().$process->getErrorOutput());
        try {
            WithdrawalRequestService::request($member, '1', 'control_race', 'earnings', $wallet);
            $this->fail('Concurrent OFF control bypassed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Withdrawals are OFF', $error->getMessage());
        }
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame($before, DB::table('financial_ledger')->count());
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->assertSame('50.00000000', $member->fresh()->available_balance);
    }

    public function test_stale_administrator_and_stale_activation_state_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'inactive']);
        $this->actingAs($admin)->post(route('admin.member-access.activate-account'), ['member_id' => $member->id, 'expected_status' => 'active'])->assertSessionHasErrors();
        DB::table('users')->where('id', $admin->id)->update(['status' => 'inactive']);
        try {
            MemberWithdrawalControl::set($admin, $member->id, false, true);
            $this->fail('Stale administrator accepted.');
        } catch (AuthorizationException) {
            $this->assertTrue(MemberWithdrawalControl::enabled($member->id));
        }
        $this->assertSame('inactive', $member->fresh()->status);
    }
}
