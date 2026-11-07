<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingPrincipalService;
use App\Services\StakingService;
use App\Services\WalletService;
use App\Services\WithdrawalMinimumPolicy as Policy;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class WithdrawalMinimumAndMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_arbitrary_manual_minimum_and_member_cannot(): void
    {
        config(['leobot.withdrawal_settings_write_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $i = 0;
        foreach (['1', '2', '3', '4', '5', '6', '10', '20', '50', '100', '7.25'] as $amount) {
            if ($i++ === 10) {
                $this->travel(61)->seconds();
            }
            $this->actingAs($admin)->post(route('admin.withdrawals.minimum'), ['minimum_usdt' => $amount])->assertSessionHasNoErrors()->assertSessionHas('status');
            $this->assertSame(0, bccomp(Policy::current(), $amount, 8));
        }
        $this->assertDatabaseCount('withdrawal_settings_audits', 11);
        $this->assertDatabaseCount('financial_ledger', 0);
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']))->post(route('admin.withdrawals.minimum'), ['minimum_usdt' => '1'])->assertForbidden();
    }

    public function test_minimum_is_enforced_and_invalid_limits_do_not_save(): void
    {
        config(['leobot.withdrawal_settings_write_enabled' => true]);
        $a = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Policy::update($a, '5');
        foreach (['0', '-1', '1e3', '5.000000001'] as $bad) {
            try {
                Policy::update($a, $bad);
                $this->fail('Invalid minimum accepted');
            } catch (\RuntimeException) {
            }
        }
        Policy::assertAmount('5');
        Policy::assertAmount('100');
        $this->expectException(\RuntimeException::class);
        Policy::assertAmount('4.99');
    }

    public function test_dashboard_has_simple_magic_link_no_profile_strip_and_quick_links_at_bottom(): void
    {
        $m = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => Hash::make('123456')]);
        $s = $this->actingAs($m)->get(route('member.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="member-profile"', $s);
        $this->assertStringContainsString('portal-menu-close', $s);
        $this->assertTrue(strpos($s, 'LumiFlowOn Quick Links') > strpos($s, 'id="account-summary"'));
    }

    public function test_email_change_does_not_require_duplicate_confirmation_input(): void
    {
        $a = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $m = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $this->actingAs($a)->post(route('admin.member-email.save'), ['member_id' => $m->id, 'old_email' => $m->email, 'new_email' => 'single-entry@example.test'])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame('single-entry@example.test', $m->fresh()->email);
    }

    public function test_both_wallets_enforce_minimum_without_debit_and_old_requests_remain_idempotent(): void
    {
        config(['leobot.withdrawal_settings_write_enabled' => true, 'leobot.withdrawals_enabled' => true, 'leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('withdrawal_settings')->where('id', 1)->update(['member_requests_enabled' => true, 'principal_release_enabled' => true, 'principal_fee_configured_at' => now(), 'principal_withdrawal_fee_percent' => '5']);
        WalletService::credit($member->id, 'available_balance', '20', 'test_fixture', 'minimum_test_credit');
        $position = app(StakingService::class)->create($member->id, '10', 'minimum_principal_stake');
        $wallet = DB::table('usdt_member_wallets')->insertGetId(['user_id' => $member->id, 'network' => 'BEP20', 'address' => '0x'.str_repeat('1', 40), 'verified_at' => now(), 'verification_method' => 'account_email_otp', 'proof_digest' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        Policy::update($admin, '5');
        foreach ([false, true] as $principal) {
            try {
                if ($principal) {
                    StakingPrincipalService::request($member, $position, '4', 'minimum_principal_4', $wallet, '5');
                } else {
                    WithdrawalRequestService::request($member, '4', 'minimum_earnings_4', 'earnings', $wallet);
                }$this->fail('Below-minimum request accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Minimum withdrawal', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->assertSame('10.00000000', $member->fresh()->available_balance);
        Policy::update($admin, '1');
        $earn = WithdrawalRequestService::request($member, '1', 'minimum_earnings_1', 'earnings', $wallet);
        $principal = StakingPrincipalService::request($member, $position, '1', 'minimum_principal_1', $wallet, '5');
        Policy::update($admin, '5');
        $this->assertSame($earn, WithdrawalRequestService::request($member, '1', 'minimum_earnings_1', 'earnings', $wallet));
        $this->assertSame($principal, StakingPrincipalService::request($member, $position, '1', 'minimum_principal_1', $wallet, '5'));
        $this->assertDatabaseCount('withdrawal_requests', 2);
        $this->assertSame('9.00000000', $member->fresh()->available_balance);
    }
}
