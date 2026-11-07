<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Contracts\UsdtWalletOwnershipVerifier;
use App\Models\User;
use App\Services\MemberPaymentSecurity;
use App\Services\StakingPrincipalService;
use App\Services\StakingService;
use App\Services\UsdtWalletChallengeService;
use App\Services\UsdtWalletEnrollmentService;
use App\Services\VerifiedDepositService;
use App\Services\WalletService;
use App\Services\WithdrawalEmailOtpService as Otp;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class MemberPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    private int $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('member_security_controls')->updateOrInsert(['id' => 1], ['wallet_changes_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        config(['leobot.withdrawals_enabled' => true, 'leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.deposits_enabled' => true, 'leobot_deposits.receiving_addresses.BEP20' => '0x'.str_repeat('2', 40)]);
        $this->member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->wallet = DB::table('usdt_member_wallets')->insertGetId(['user_id' => $this->member->id, 'network' => 'BEP20', 'address' => '0x'.str_repeat('1', 40), 'verified_at' => now(), 'verification_method' => 'signed_challenge', 'proof_digest' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('withdrawal_settings')->where('id', 1)->update(['member_requests_enabled' => true, 'principal_release_enabled' => true, 'principal_fee_configured_at' => now(), 'principal_withdrawal_fee_percent' => '5']);
        WalletService::credit($this->member->id, 'available_balance', '100', 'test_fixture', 'otp_fixture_credit');
    }

    private function data(array $changes = []): array
    {
        return array_replace(['amount' => '10', 'usdt_wallet_id' => $this->wallet, 'request_key' => 'otp-withdrawal-request-1'], $changes);
    }

    private function issue(array $data): array
    {
        $code = '';
        Mail::shouldReceive('raw')->andReturnUsing(function ($text, $callback) use (&$code): void {
            preg_match('/OTP is (\d{6})/', $text, $m);
            $code = $m[1];
            $message = new Message(new Email);
            $callback($message);
            $this->assertSame($this->member->email, $message->getSymfonyMessage()->getTo()[0]->getAddress());
        });

        return [Otp::issue($this->member, $data), &$code];
    }

    private function execute(array $data, int $id, string $code): int
    {
        return Otp::execute($this->member, $data, $id, $code, fn () => WithdrawalRequestService::request($this->member, $data['amount'], $data['request_key'], 'earnings', (int) $data['usdt_wallet_id']));
    }

    public function test_reused_member_reference_does_not_block_distinct_deposits_and_admin_can_find_identity(): void
    {
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        foreach (['10', '60'] as $i => $amount) {
            $id = VerifiedDepositService::submit($this->member->id, $amount, 'BEP20', '0x'.str_repeat((string) ($i + 3), 64), 'deposit_search_test_'.$i);
            VerifiedDepositService::approve($this->admin->id, $id, 'TX656935');
        }
        $this->assertSame('70.00000000', $this->member->fresh()->activation_balance);
        $this->assertSame(2, DB::table('member_deposit_requests')->where('status', 'approved')->count());
        $this->assertSame(2, DB::table('member_deposit_requests')->distinct()->count('verification_reference'));
        $this->actingAs($this->admin)->get(route('admin.deposit-review.index', ['status' => 'approved', 'q' => $this->member->username]))->assertOk()->assertSee($this->member->username)->assertSee($this->member->email)->assertSee($this->member->name)->assertSee('0x'.str_repeat('3', 64));
    }

    public function test_pending_notification_is_admin_only_and_counts_requests(): void
    {
        VerifiedDepositService::submit($this->member->id, '10', 'BEP20', '0x'.str_repeat('4', 64), 'notify-deposit-1');
        $this->actingAs($this->admin)->getJson(route('admin.deposit-notifications'))->assertOk()->assertJson(['pending' => 1]);
        $this->get('/admin/dashboard')->assertSee('pending-deposit-count', false);
        $this->actingAs($this->member)->getJson(route('admin.deposit-notifications'))->assertForbidden();
    }

    public function test_staking_presets_success_and_rewards_funding_are_visible(): void
    {
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $this->actingAs($this->member)->get('/member/staking')->assertOk()->assertSee('10 USDT')->assertSee('100 USDT');
        $this->from('/member/staking')->post('/member/staking', ['amount' => '10', 'request_key' => 'staking_ui_rewards_123', 'funding_source' => 'rewards'])->assertSessionHas('status');
        $this->assertDatabaseHas('staking_positions', ['user_id' => $this->member->id, 'funding_wallet' => 'available_balance', 'amount' => '10']);
        $this->assertSame('90.00000000', $this->member->fresh()->available_balance);
    }

    public function test_otp_is_required_by_http_and_form_has_send_and_input(): void
    {
        $this->actingAs($this->member)->get('/member/withdrawals')->assertOk()->assertSee('Send Email OTP')->assertSee('name="withdrawal_otp"', false);
        $this->post('/member/withdrawals', $this->data())->assertSessionHasErrors(['withdrawal_otp', 'otp_challenge_id']);
        $this->assertDatabaseCount('withdrawal_requests', 0);
    }

    public function test_valid_otp_creates_one_locked_request_and_replay_fails(): void
    {
        $data = $this->data();
        [$id,$code] = $this->issue($data);
        $wid = $this->execute($data, $id, $code);
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $wid, 'locked_payment_address' => '0x'.str_repeat('1', 40)]);
        $this->assertSame('90.00000000', $this->member->fresh()->available_balance);
        try {
            $this->execute($data, $id, $code);
            $this->fail('Replay accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('withdrawal_requests', 1);
            $this->assertSame('90.00000000', $this->member->fresh()->available_balance);
        }
    }

    public function test_invalid_otp_attempt_limit_and_expiry_hold_no_funds(): void
    {
        $data = $this->data();
        [$id,$code] = $this->issue($data);
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->execute($data, $id, '000000');
                $this->fail('Wrong code accepted');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(5, (int) DB::table('withdrawal_email_challenges')->where('id', $id)->value('attempts'));
        try {
            $this->execute($data, $id, $code);
            $this->fail('Locked code accepted');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->assertSame('100.00000000', $this->member->fresh()->available_balance);
    }

    public function test_changed_amount_or_email_or_expired_code_is_rejected(): void
    {
        $data = $this->data();
        [$id,$code] = $this->issue($data);
        try {
            $this->execute($this->data(['amount' => '20']), $id, $code);
            $this->fail('Changed amount accepted');
        } catch (ValidationException) {
        }
        $this->member->forceFill(['email' => 'new-address@example.test'])->save();
        try {
            $this->execute($data, $id, $code);
            $this->fail('Changed email accepted');
        } catch (ValidationException) {
        }
        DB::table('withdrawal_email_challenges')->where('id', $id)->update(['expires_at' => now()->subSecond()]);
        try {
            $this->execute($data, $id, $code);
            $this->fail('Expired code accepted');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('withdrawal_requests', 0);
    }

    public function test_resend_cooldown_and_mail_failure_invalidate_challenge(): void
    {
        $data = $this->data();
        $this->issue($data);
        try {
            Otp::issue($this->member, $data);
            $this->fail('Cooldown bypass');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('60 seconds', $e->getMessage());
        }
        $this->assertDatabaseCount('withdrawal_email_challenges', 1);
    }

    public function test_mail_failure_invalidates_otp_and_keeps_balances(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Test delivery failure'));
        try {
            Otp::issue($this->member, $this->data());
            $this->fail('Failed mail accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unable to send withdrawal email', $e->getMessage());
        }
        $this->assertNotNull(DB::table('withdrawal_email_challenges')->latest('id')->value('consumed_at'));
        $this->assertSame('100.00000000', $this->member->fresh()->available_balance);
    }

    public function test_failed_hold_rolls_back_otp_consumption_and_can_retry_same_details(): void
    {
        $data = $this->data(['amount' => '200']);
        [$id,$code] = $this->issue($data);
        try {
            $this->execute($data, $id, $code);
            $this->fail('Insufficient hold accepted');
        } catch (\RuntimeException) {
        }
        $this->assertNull(DB::table('withdrawal_email_challenges')->where('id', $id)->value('consumed_at'));
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->assertSame('100.00000000', $this->member->fresh()->available_balance);
    }

    public function test_principal_otp_is_bound_to_stake_and_fee_and_existing_address_stays_locked(): void
    {
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $position = (new StakingService)->create($this->member->id, '40', 'principal_otp_stake_123');
        $data = $this->data(['position_id' => $position, 'expected_fee_percent' => '5']);
        [$id,$code] = $this->issue($data);
        $wid = Otp::execute($this->member, $data, $id, $code, fn () => StakingPrincipalService::request($this->member, $position, '10', $data['request_key'], $this->wallet, '5'));
        $second = DB::table('usdt_member_wallets')->insertGetId(['user_id' => $this->member->id, 'network' => 'BEP20', 'address' => '0x'.str_repeat('5', 40), 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        MemberPaymentSecurity::select($this->member->id, $second, 'BEP20');
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $wid, 'usdt_member_wallet_id' => $this->wallet, 'locked_payment_address' => '0x'.str_repeat('1', 40), 'net_amount' => '9.5']);
        $this->assertSame($second, MemberPaymentSecurity::selectedId($this->member->id, 'BEP20'));
    }

    public function test_profile_wallet_changes_gate_requires_admin_and_blocks_signed_enrollment(): void
    {
        MemberPaymentSecurity::toggle($this->admin, false);
        $this->actingAs($this->member)->postJson('/member/usdt-wallets/challenge', ['network' => 'BEP20', 'address' => '0x'.str_repeat('2', 40)])->assertStatus(422);
        $this->post('/admin/profile-wallet-controls', ['enabled' => true])->assertForbidden();
        $service = app(UsdtWalletChallengeService::class);
        $challenge = $service->issue($this->member->id, 'BEP20', '0x'.str_repeat('2', 40));
        try {
            app(UsdtWalletEnrollmentService::class)->enroll($this->member->id, $challenge['challenge_id'], $challenge['message'], 'invalid');
            $this->fail('Disabled update accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('disabled', $e->getMessage());
        }
        $this->assertSame($this->wallet, MemberPaymentSecurity::selectedId($this->member->id, 'BEP20'));
        $this->assertDatabaseCount('member_security_audits', 1);
    }

    public function test_email_send_and_withdrawal_http_flow_verifies_otp_before_holding_funds(): void
    {
        $code = '';
        Mail::shouldReceive('raw')->once()->andReturnUsing(function ($text, $callback) use (&$code): void {
            preg_match('/OTP is (\d{6})/', $text, $matches);
            $code = $matches[1];
        });
        $response = $this->actingAs($this->member)->postJson(route('member.withdrawal-otp.send'), $this->data())->assertOk();
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->from('/member/withdrawals')->post('/member/withdrawals', $this->data() + ['otp_challenge_id' => $response->json('challenge_id'), 'withdrawal_otp' => $code])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseHas('withdrawal_requests', ['user_id' => $this->member->id, 'gross_amount' => '10', 'locked_payment_address' => '0x'.str_repeat('1', 40)]);
        $this->assertSame('90.00000000', $this->member->fresh()->available_balance);
    }

    public function test_new_signed_profile_wallet_replaces_selection_and_invalidates_old_wallet_otp(): void
    {
        [$id,$code] = $this->issue($this->data());
        $verifier = $this->createStub(UsdtWalletOwnershipVerifier::class);
        $verifier->method('verify')->willReturn(true);
        $this->app->instance(UsdtWalletOwnershipVerifier::class, $verifier);
        $challenge = app(UsdtWalletChallengeService::class)->issue($this->member->id, 'BEP20', '0x'.str_repeat('6', 40));
        $new = app(UsdtWalletEnrollmentService::class)->enroll($this->member->id, $challenge['challenge_id'], $challenge['message'], 'test-only-signature');
        $this->assertSame($new, MemberPaymentSecurity::selectedId($this->member->id, 'BEP20'));
        $this->assertSame(1, MemberPaymentSecurity::wallets($this->member->id)->count());
        try {
            $this->execute($this->data(), $id, $code);
            $this->fail('Outdated profile wallet accepted');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->assertSame('100.00000000', $this->member->fresh()->available_balance);
    }

    public function test_wrong_otp_is_not_flashed_and_the_same_request_can_be_retried(): void
    {
        $data = $this->data();
        [$id,$code] = $this->issue($data);
        $this->actingAs($this->member)->from('/member/withdrawals')->post('/member/withdrawals', $data + ['otp_challenge_id' => $id, 'withdrawal_otp' => '000000'])->assertSessionHasErrors('withdrawal_otp');
        $this->assertNull(session()->getOldInput('withdrawal_otp'));
        $this->assertSame($data['request_key'], session()->getOldInput('request_key'));
        $this->assertSame(1, (int) DB::table('withdrawal_email_challenges')->where('id', $id)->value('attempts'));
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->post('/member/withdrawals', $data + ['otp_challenge_id' => $id, 'withdrawal_otp' => $code])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('withdrawal_requests', 1);
    }

    public function test_inactive_registered_member_can_stake_approved_deposit_and_withdraw_principal_without_package(): void
    {
        $this->member->forceFill(['status' => 'inactive'])->save();
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        $deposit = VerifiedDepositService::submit($this->member->id, '10', 'BEP20', '0x'.str_repeat('b', 64), 'inactive_realistic_deposit');
        VerifiedDepositService::approve($this->admin->id, $deposit);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $service = new StakingService;
        $stake = $service->create($this->member->id, '10', 'inactive_deposit_stake_1', 'activation_balance');
        $this->assertSame($stake, $service->create($this->member->id, '10', 'inactive_deposit_stake_1', 'activation_balance'));
        $this->assertSame('inactive', $this->member->fresh()->status);
        $data = $this->data(['position_id' => $stake, 'expected_fee_percent' => '5']);
        [$otp,$code] = $this->issue($data);
        $withdrawal = Otp::execute($this->member, $data, $otp, $code, fn () => StakingPrincipalService::request($this->member, $stake, '10', $data['request_key'], $this->wallet, '5'));
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $withdrawal, 'wallet_type' => 'staking_principal', 'net_amount' => '9.5']);
        $this->assertSame('inactive', $this->member->fresh()->status);

    }

    public function test_inactive_member_cannot_stake_pending_or_unverified_credit_or_reuse_approved_deposit(): void
    {
        $this->member->forceFill(['status' => 'inactive'])->save();
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        WalletService::credit($this->member->id, 'activation_balance', '100', 'test_fixture', 'unverified_stake_credit');
        try {
            (new StakingService)->create($this->member->id, '10', 'unverified_inactive_stake', 'activation_balance');
            $this->fail('Unverified money accepted');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('staking_positions', 0);
        $deposit = VerifiedDepositService::submit($this->member->id, '10', 'BEP20', '0x'.str_repeat('c', 64), 'inactive_pending_deposit');
        try {
            (new StakingService)->create($this->member->id, '10', 'pending_inactive_stake', 'activation_balance');
            $this->fail('Pending receipt accepted');
        } catch (\RuntimeException) {
        }
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        VerifiedDepositService::approve($this->admin->id, $deposit);
        (new StakingService)->create($this->member->id, '10', 'approved_inactive_stake', 'activation_balance');
        try {
            (new StakingService)->create($this->member->id, '1', 'spent_inactive_stake_2', 'activation_balance');
            $this->fail('Approved deposit reused');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('staking_positions', 1);
    }

    public function test_other_debits_reduce_inactive_member_verified_deposit_budget(): void
    {
        $this->member->forceFill(['status' => 'inactive'])->save();
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        $id = VerifiedDepositService::submit($this->member->id, '10', 'BEP20', '0x'.str_repeat('d', 64), 'inactive_budget_deposit');
        VerifiedDepositService::approve($this->admin->id, $id);
        WalletService::debit($this->member->id, 'activation_balance', '5', 'test_transfer', 'budget_already_spent');
        WalletService::credit($this->member->id, 'activation_balance', '100', 'test_fixture', 'budget_unverified_credit');
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        try {
            (new StakingService)->create($this->member->id, '10', 'inactive_overspend_budget', 'activation_balance');
            $this->fail('Previously spent deposit reused');
        } catch (\RuntimeException) {
        }
        (new StakingService)->create($this->member->id, '5', 'inactive_remaining_budget', 'activation_balance');
        $this->assertDatabaseCount('staking_positions',1);
    }
}
