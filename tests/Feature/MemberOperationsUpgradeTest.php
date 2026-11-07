<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncomeDistributionService;
use App\Services\MagicQualificationService;
use App\Services\MemberActionOtpService as Otp;
use App\Services\MemberFundTransferService;
use App\Services\MemberTransferPolicy;
use App\Services\StakingService;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MemberOperationsUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $recipient;

    private User $admin;

    private string $code = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true]);
        DB::table('member_security_controls')->updateOrInsert(['id' => 1], ['wallet_changes_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('member_transfer_settings')->updateOrInsert(['id' => 1], ['enabled' => true, 'minimum_usdt' => '1', 'maximum_usdt' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $this->member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        $this->recipient = User::factory()->create(['role' => 'member', 'status' => 'inactive']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Mail::shouldReceive('raw')->andReturnUsing(function ($text): void {
            preg_match('/OTP is (\d{6})/', $text, $m);
            $this->code = $m[1];
        });
    }

    private function wallet(): array
    {
        return ['network' => 'BEP20', 'address' => '0x'.str_repeat('1', 40)];
    }

    private function transfer(): array
    {
        return ['recipient_id' => $this->recipient->id, 'recipient_username' => $this->recipient->username, 'amount' => '1.00000000', 'request_key' => 'member_upgrade_transfer_1'];
    }

    public function test_email_otp_saves_profile_without_wallet_extension_and_prevents_replay(): void
    {
        $this->actingAs($this->member)->get('/member/usdt-wallets/page')->assertOk()->assertSee('Send Email OTP')->assertDontSee('MetaMask')->assertDontSee('TronLink');
        $response = $this->postJson(route('member.wallet-otp.send'), $this->wallet())->assertOk();
        $proof = ['otp_challenge_id' => $response->json('challenge_id'), 'email_otp' => $this->code] + $this->wallet();
        $this->postJson(route('member.wallet-otp.save'), $proof)->assertOk()->assertJsonPath('message', 'Address changed successfully. आपका wallet address सफलतापूर्वक सेव हो गया है।');
        $this->assertDatabaseHas('usdt_member_wallets', ['user_id' => $this->member->id, 'address' => $this->wallet()['address'], 'verification_method' => 'account_email_otp']);
        $this->assertDatabaseHas('member_wallet_profiles', ['user_id' => $this->member->id, 'network' => 'BEP20']);
        $this->postJson(route('member.wallet-otp.save'), $proof)->assertUnprocessable();
        $this->assertDatabaseCount('member_wallet_profiles', 1);
    }

    public function test_wallet_changed_address_wrong_code_and_admin_off_are_rejected(): void
    {
        $id = Otp::issue($this->member, 'profile_wallet', $this->wallet());
        $this->actingAs($this->member)->postJson(route('member.wallet-otp.save'), $this->wallet() + ['otp_challenge_id' => $id, 'email_otp' => '000000'])->assertUnprocessable();
        $this->postJson(route('member.wallet-otp.save'), array_replace($this->wallet(), ['address' => '0x'.str_repeat('2', 40), 'otp_challenge_id' => $id, 'email_otp' => $this->code]))->assertUnprocessable();
        DB::table('member_security_controls')->where('id', 1)->update(['wallet_changes_enabled' => false]);
        $this->postJson(route('member.wallet-otp.save'), $this->wallet() + ['otp_challenge_id' => $id, 'email_otp' => $this->code])->assertUnprocessable();
        $this->assertDatabaseCount('member_wallet_profiles', 0);
    }

    public function test_transfer_verifies_recipient_and_requires_bound_otp_then_shows_receipt(): void
    {
        WalletService::credit($this->member->id, 'available_balance', '10', 'test_fixture', 'transfer_fixture_credit');
        $this->actingAs($this->member)->getJson(route('member.transfer.recipient', ['recipient' => $this->recipient->username]))->assertOk()->assertJson(['verified' => true, 'username' => $this->recipient->username, 'name' => $this->recipient->name])->assertJsonMissing(['email' => $this->recipient->email]);
        $data = ['recipient' => $this->recipient->username, 'amount' => '1', 'request_key' => 'member_upgrade_transfer_1'];
        $this->post('/member/fund-transfer', $data)->assertSessionHasErrors();
        $sent = $this->postJson(route('member.transfer-otp.send'), $data)->assertOk();
        $proof = ['otp_challenge_id' => $sent->json('challenge_id'), 'email_otp' => $this->code];
        $this->post('/member/fund-transfer', $data + $proof)->assertSessionHasNoErrors()->assertSessionHas('transfer_receipt_id')->assertSessionHas('status');
        $this->assertSame('9.00000000', $this->member->fresh()->available_balance);
        $this->assertSame('1.00000000', $this->recipient->fresh()->available_balance);
        $this->get(route('member.deposit-transfer.index'))->assertOk()->assertSee('member_upgrade_transfer_1');
        $this->post('/member/fund-transfer', $data + $proof)->assertSessionHasErrors();
        $this->assertDatabaseCount('member_transfer_receipts', 1);
        $this->assertSame(2, DB::table('financial_ledger')->where('transaction_key', 'like', 'member-transfer:%')->count());
    }

    public function test_changed_transfer_amount_and_expired_otp_never_debit(): void
    {
        WalletService::credit($this->member->id, 'available_balance', '10', 'test_fixture', 'changed_transfer_credit');
        $d = $this->transfer();
        $id = Otp::issue($this->member, 'fund_transfer', $d);
        try {
            Otp::execute($this->member, 'fund_transfer', array_replace($d, ['amount' => '2.00000000']), $id, $this->code, fn () => MemberFundTransferService::transfer($this->member->id, $this->recipient->id, '2', $d['request_key']));
            $this->fail('Changed amount accepted');
        } catch (ValidationException) {
        }
        $this->travel(6)->minutes();
        try {
            Otp::execute($this->member, 'fund_transfer', $d, $id, $this->code, fn () => null);
            $this->fail('Expired code accepted');
        } catch (ValidationException) {
        }
        $this->assertSame('10.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseCount('member_transfer_receipts', 0);
    }

    public function test_transfer_limits_off_and_recipient_failure_preserve_both_balances(): void
    {
        WalletService::credit($this->member->id, 'available_balance', '10', 'test_fixture', 'limits_transfer_credit');
        MemberTransferPolicy::update($this->admin, true, '1', '2');
        foreach (['0.9', '3'] as $n) {
            try {
                MemberFundTransferService::transfer($this->member->id, $this->recipient->id, $n, 'limits_transfer_'.$n);
                $this->fail('Invalid amount allowed');
            } catch (\RuntimeException|\InvalidArgumentException) {
            }
        }
        MemberTransferPolicy::update($this->admin, false, '1', null);
        try {
            MemberFundTransferService::transfer($this->member->id, $this->recipient->id, '1', 'disabled_transfer_1');
            $this->fail('Disabled transfer allowed');
        } catch (\RuntimeException) {
        }
        $this->assertSame('10.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseCount('member_transfer_receipts', 0);
        $this->actingAs($this->member)->get(route('admin.transfer-controls'))->assertForbidden();
        $this->getJson(route('member.transfer.recipient', ['recipient' => $this->member->username]))->assertUnprocessable();
    }

    public function test_delete_member_revokes_access_but_preserves_ledger_and_balance(): void
    {
        WalletService::credit($this->member->id, 'available_balance', '10', 'test_fixture', 'retirement_fixture_credit');
        $this->actingAs($this->admin)->get(route('admin.members.deletion'))->assertOk()->assertSee($this->member->username);
        $this->post(route('admin.members.delete-selected'), ['member_id' => $this->member->id])->assertRedirect(route('admin.members.deletion'));
        $this->assertNotNull($this->member->fresh()->deleted_at);
        $this->assertSame('10.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseHas('financial_ledger', ['user_id' => $this->member->id, 'transaction_key' => 'retirement_fixture_credit']);
        $this->post(route('admin.members.delete-selected'), ['member_id' => $this->admin->id])->assertNotFound();
    }

    public function test_single_fifty_stake_qualifies_but_twenty_plus_thirty_does_not(): void
    {
        WalletService::credit($this->member->id, 'available_balance', '100', 'test_fixture', 'magic_single_fixture');
        app(StakingService::class)->create($this->member->id, '20', 'magic_small_twenty');
        app(StakingService::class)->create($this->member->id, '30', 'magic_small_thirty');
        $this->assertFalse(MagicQualificationService::inspect($this->member->id)['eligible']);
        app(StakingService::class)->create($this->member->id, '50', 'magic_single_fifty');
        $q = MagicQualificationService::inspect($this->member->id);
        $this->assertTrue($q['eligible']);
        $this->assertNotNull($q['qualified_at']);
        $this->assertSame('100.00000000', IncomeDistributionService::weights()[$this->member->id]);
    }

    public function test_income_largest_remainder_conserves_profit_and_never_bypasses_approval(): void
    {
        $this->assertSame([1 => '3.33333334', 2 => '3.33333333', 3 => '3.33333333'], IncomeDistributionService::allocate('10', [1 => '1', 2 => '1', 3 => '1']));
        $this->assertSame([1 => '2.50000000', 2 => '7.50000000'], IncomeDistributionService::allocate('10', [1 => '10', 2 => '30']));
        config(['leobot.self_income_settlement_enabled' => false]);
        $this->expectException(\RuntimeException::class);
        IncomeDistributionService::distribute($this->admin, 1);
    }

    public function test_email_login_is_case_insensitive_and_keeps_pin_and_block_checks(): void
    {
        config(['leobot_auth.captcha_enabled' => false]);
        $this->post('/login', ['username' => strtoupper($this->member->email), 'password' => 'password'])->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($this->member);
        $this->post('/logout');
        DB::table('users')->where('id', $this->member->id)->update(['is_blocked' => true]);
        $this->post('/login', ['username' => $this->member->email, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_distribution_submission_snapshots_remaining_principal_and_retries_without_creating_money(): void
    {
        config(['leobot.staking_profit_enabled' => true]);
        DB::table('users')->where('id', $this->recipient->id)->update(['status' => 'active']);
        foreach ([[$this->member, '10'], [$this->recipient, '30']] as [$u,$amount]) {
            WalletService::credit($u->id, 'available_balance', $amount, 'test_fixture', 'distribution_fixture_'.$u->id);
            app(StakingService::class)->create($u->id, $amount, 'distribution_stake_'.$u->id);
        }
        $id = IncomeDistributionService::submit($this->admin, '10', 'DIST-REAL-PROFIT-TEST', 'weekly', '2026-11-07');
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'pending_verification', 'amount' => '10']);
        $this->assertDatabaseHas('staking_profit_allocations', ['statement_id' => $id, 'user_id' => $this->member->id, 'amount' => '2.5']);
        $this->assertDatabaseHas('staking_profit_allocations', ['statement_id' => $id, 'user_id' => $this->recipient->id, 'amount' => '7.5']);
        $this->assertSame($id, IncomeDistributionService::submit($this->admin, '10', 'DIST-REAL-PROFIT-TEST', 'weekly', '2026-11-07'));
        $this->assertDatabaseCount('income_distribution_requests', 1);
        $this->assertSame('0.00000000', $this->member->fresh()->available_balance);
        $this->assertDatabaseCount('profit_sharing_fundings', 0);
        try {
            IncomeDistributionService::submit($this->admin, '11', 'DIST-REAL-PROFIT-TEST', 'weekly', '2026-11-07');
            $this->fail('Changed statement accepted');
        } catch (\RuntimeException) {
        }
        $this->actingAs($this->admin)->get(route('admin.income-distribution'))->assertOk()->assertSee('Daily')->assertSee('Weekly')->assertSee('Monthly');
        $this->post(route('admin.income-distribution.reserve', $id), ['verified_funds_received' => 1])->assertSessionHasErrors();
        $this->assertDatabaseCount('profit_sharing_fundings', 0);
    }

    public function test_transfer_credit_failure_rolls_back_debit_and_otp_consumption(): void
    {
        WalletService::credit($this->member->id, 'available_balance', '10', 'test_fixture', 'rollback_transfer_credit');
        $d = $this->transfer();
        $id = Otp::issue($this->member, 'fund_transfer', $d);
        DB::statement("ALTER TABLE financial_ledger ADD CONSTRAINT upgrade_reject_transfer CHECK (entry_type <> 'member_transfer_in')");
        try {
            try {
                Otp::execute($this->member, 'fund_transfer', $d, $id, $this->code, fn () => MemberFundTransferService::transfer($this->member->id, $this->recipient->id, $d['amount'], $d['request_key']));
                $this->fail('Broken credit accepted');
            } catch (QueryException) {
            }
            $this->assertSame('10.00000000', $this->member->fresh()->available_balance);
            $this->assertSame('0.00000000', $this->recipient->fresh()->available_balance);
            $this->assertDatabaseCount('member_transfer_receipts', 0);
            $this->assertNull(DB::table('member_action_challenges')->where('id', $id)->value('consumed_at'));
        } finally {
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT upgrade_reject_transfer');
        }
    }
}
