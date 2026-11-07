<?php

namespace Tests\Feature;

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\ActivationService;
use App\Services\MagicIncomeSettlementService;
use App\Services\MemberAccountAdministration as Accounts;
use App\Services\StakingProfitService;
use App\Services\VerifiedDepositService;
use App\Services\WalletService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MemberAccountAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'security_pin' => Hash::make('123456')]);
    }

    public function test_admin_can_activate_from_verified_deposits_once_and_set_inactive_without_refund(): void
    {
        config(['leobot.admin_member_activation_enabled' => true, 'leobot.production_financial_approval' => true]);
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        config(['leobot.deposits_enabled' => true, 'leobot_deposits.receiving_addresses.BEP20' => '0x'.str_repeat('2', 40)]);
        $id = VerifiedDepositService::submit($this->member->id, '10', 'BEP20', '0x'.str_repeat('a', 64), 'admin_activation_deposit');
        VerifiedDepositService::approve($this->admin->id, $id);
        $this->actingAs($this->admin)->get(route('admin.member-access', ['member' => $this->member->email]))->assertOk()->assertSee($this->member->username)->assertSee('Activate from balance');
        $d = ['member_id' => $this->member->id, 'status' => 'active', 'expected_status' => 'inactive', 'amount' => '10', 'activation_key' => (string) Str::uuid()];
        $this->post(route('admin.member-access.save'), $d)->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->post(route('admin.member-access.save'), $d)->assertSessionHasNoErrors();
        $this->assertSame('active', $this->member->fresh()->status);
        $this->assertSame('0.00000000', $this->member->fresh()->activation_balance);
        $this->assertSame('10.00000000', $this->member->fresh()->total_investment);
        $this->assertDatabaseCount('activation_packages', 1);
        $this->assertSame(1, DB::table('financial_ledger')->where('entry_type', 'package_activation')->count());
        $this->assertDatabaseHas('member_security_audits', ['action' => 'admin_verified_activation']);
        $this->post(route('admin.member-access.save'), ['member_id' => $this->member->id, 'status' => 'inactive', 'expected_status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $this->member->fresh()->status);
        $this->assertSame('0.00000000', $this->member->fresh()->activation_balance);
        $this->post(route('admin.member-access.save'), $d)->assertSessionHasErrors();
        $this->assertSame('inactive', $this->member->fresh()->status);
    }

    public function test_member_blocked_retired_and_stale_admin_cannot_activate(): void
    {
        $this->actingAs($this->member)->post(route('admin.member-access.save'), ['member_id' => $this->member->id, 'status' => 'active', 'expected_status' => 'inactive'])->assertForbidden();
        DB::table('users')->where('id', $this->member->id)->update(['is_blocked' => true]);
        try {
            Accounts::status($this->admin, $this->member->id, 'active', 'inactive');
            $this->fail('Blocked account activated');
        } catch (\RuntimeException) {
        }
        DB::table('users')->where('id', $this->member->id)->update(['is_blocked' => false, 'deleted_at' => now()]);
        try {
            Accounts::status($this->admin, $this->member->id, 'active', 'inactive');
            $this->fail('Retired account activated');
        } catch (\RuntimeException) {
        }
        DB::table('users')->where('id', $this->admin->id)->update(['status' => 'inactive']);
        $this->expectException(AuthorizationException::class);
        Accounts::status($this->admin, $this->member->id, 'active', 'inactive');
    }

    public function test_email_change_is_unique_audited_and_invalidates_old_reset_and_access_tokens(): void
    {
        $this->member->createToken('disposable');
        DB::table('password_reset_otps')->insert(['user_id' => $this->member->id, 'otp_hash' => Hash::make('123456'), 'attempts' => 0, 'expires_at' => now()->addMinutes(5), 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $old = $this->member->email;
        $this->actingAs($this->admin)->get(route('admin.member-email', ['member' => $this->member->username]))->assertOk()->assertSee($old);
        $this->post(route('admin.member-email.save'), ['member_id' => $this->member->id, 'old_email' => $old, 'new_email' => 'Changed@example.test', 'new_email_confirmation' => 'Changed@example.test'])->assertRedirect(route('admin.member-email', ['member' => (string) $this->member->id]))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame('changed@example.test', $this->member->fresh()->email);

        $this->assertSame(1, (int) $this->member->fresh()->member_login_version);
        $this->assertDatabaseCount('password_reset_otps', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('member_security_audits', ['action' => 'admin_member_email']);
        $this->post(route('admin.member-email.save'), ['member_id' => $this->member->id, 'old_email' => 'changed@example.test', 'new_email' => $this->admin->email, 'new_email_confirmation' => $this->admin->email])->assertSessionHasErrors('email');
        $this->assertSame('changed@example.test', $this->member->fresh()->email);
    }

    public function test_email_change_requires_current_email_and_rejects_other_admin_targets(): void
    {
        try {
            Accounts::email($this->admin, $this->member->id, 'wrong@example.test', 'new@example.test');
            $this->fail('Stale email accepted');
        } catch (\RuntimeException) {
        }
        try {
            Accounts::email($this->admin, $this->admin->id, $this->admin->email, 'new@example.test');
            $this->fail('Admin target accepted');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('member_security_audits', 0);
    }

    public function test_account_write_rolls_back_when_audit_fails(): void
    {
        DB::statement("ALTER TABLE member_security_audits ADD CONSTRAINT account_test_reject CHECK (action <> 'admin_member_email')");
        try {
            try {
                Accounts::email($this->admin, $this->member->id, $this->member->email, 'rollback@example.test');
                $this->fail('Audit failure accepted');
            } catch (QueryException) {
            }$this->assertSame($this->member->email, $this->member->fresh()->email);
            $this->assertSame(0, (int) $this->member->fresh()->member_login_version);
        } finally {
            DB::statement('ALTER TABLE member_security_audits DROP CONSTRAINT account_test_reject');
        }
    }

    public function test_profit_reject_is_audited_idempotent_and_cannot_reject_funded_statements(): void
    {
        $id = DB::table('staking_profit_statements')->insertGetId(['amount' => '10', 'evidence_reference' => 'ACCOUNT-PROFIT-TEST', 'submitted_by' => $this->admin->id, 'status' => 'pending_verification', 'distribution_version' => 'unified_67_30_3', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->admin)->get(route('admin.staking.profit.index'))->assertOk()->assertSee('Verify')->assertSee('Reject')->assertDontSee('ACCOUNT-PROFIT-TEST')->assertDontSee('Independent member reviewers');
        $this->post(route('admin.staking.profit.reject', $id))->assertSessionHasNoErrors()->assertSessionHas('status');
        StakingProfitService::reject($this->admin, $id);
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'rejected']);
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'profit_statement_rejected')->count());
        $this->assertDatabaseCount('financial_ledger', 0);
        DB::table('staking_profit_statements')->where('id', $id)->update(['status' => 'reserved']);
        $this->post(route('admin.staking.profit.reject', $id))->assertSessionHasErrors();
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $id, 'status' => 'reserved']);
    }

    public function test_admin_activation_rejects_unverified_balance_and_insufficient_or_disabled_requests(): void
    {
        config(['leobot.admin_member_activation_enabled' => true, 'leobot.production_financial_approval' => true]);
        WalletService::credit($this->member->id, 'activation_balance', '10', 'test_fixture', 'unverified_activation_fixture');
        $this->actingAs($this->admin)->post(route('admin.member-access.save'), ['member_id' => $this->member->id, 'status' => 'active', 'expected_status' => 'inactive', 'amount' => '10', 'activation_key' => (string) Str::uuid()])->assertSessionHasErrors();
        $this->assertDatabaseCount('activation_packages', 0);
        $this->assertSame('10.00000000', $this->member->fresh()->activation_balance);
    }

    public function test_same_admin_can_verify_once_only_with_explicit_policy_and_balanced_reserves(): void
    {
        config(['leobot.admin_profit_verification_enabled' => true, 'leobot.production_financial_approval' => true, 'leobot.staking_profit_enabled' => true]);
        DB::table('profit_sharing_settings')->where('id', 1)->update(['combined_funding_enabled' => true]);
        $id = DB::table('staking_positions')->insertGetId(['user_id' => $this->member->id, 'amount' => '10', 'request_key' => 'admin_manual_profit_stake', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', $this->member->id)->update(['status' => 'active']);
        $statement = StakingProfitService::submit($this->admin, '10', 'ADMIN-MANUAL-VERIFY-001', [$this->member->id => '10']);
        $this->actingAs($this->admin)->post(route('admin.staking.profit.verify', $statement))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->post(route('admin.staking.profit.verify', $statement))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staking_profit_statements', ['id' => $statement, 'status' => 'reserved', 'verified_by' => $this->admin->id, 'verification_method' => 'admin_manual_confirmation']);
        $this->assertDatabaseCount('profit_sharing_fundings', 1);
        $this->assertSame(1, DB::table('member_security_audits')->where('action', 'profit_admin_verified')->count());
        $this->assertDatabaseHas('profit_sharing_reserve_accounts', ['self_balance' => '6.7', 'magic_balance' => '0.3']);
        config(['leobot_magic.magic_matrix_settlement_enabled' => true, 'leobot_magic.production_financial_approval' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['settlement_enabled' => true]);
        DB::table('magic_matrix_level_rates')->update(['pool_percent' => '5']);
        $this->app['env'] = 'production';
        try {
            $fund = (int) DB::table('staking_profit_statements')->where('id', $statement)->value('profit_sharing_funding_id');
            $result = MagicIncomeSettlementService::settleSource($this->admin, $fund, $this->member->id);
            $this->assertSame('0.00000000', $result['credited_usdt']);
            $this->assertDatabaseCount('magic_income_entitlements', 20);
        } finally {
            $this->app['env'] = 'testing';
        }

    }

    public function test_admin_activation_audit_failure_rolls_back_package_debit_and_status(): void
    {
        config(['leobot.admin_member_activation_enabled' => true, 'leobot.production_financial_approval' => true, 'leobot.deposits_enabled' => true, 'leobot_deposits.receiving_addresses.BEP20' => '0x'.str_repeat('2', 40)]);
        $chain = $this->createStub(UsdtChainVerifier::class);
        $chain->method('verified')->willReturn(true);
        $this->app->instance(UsdtChainVerifier::class, $chain);
        $deposit = VerifiedDepositService::submit($this->member->id, '10', 'BEP20', '0x'.str_repeat('b', 64), 'activation_rollback_deposit');
        VerifiedDepositService::approve($this->admin->id, $deposit);
        DB::statement("ALTER TABLE member_security_audits ADD CONSTRAINT activation_audit_test CHECK (action <> 'admin_verified_activation')");
        try {
            try {
                ActivationService::activateByAdmin($this->admin, $this->member, '10', (string) Str::uuid());
                $this->fail('Audit failure accepted');
            } catch (QueryException) {
            }$this->assertDatabaseCount('activation_packages', 0);
            $this->assertSame('10.00000000', $this->member->fresh()->activation_balance);
            $this->assertSame('0.00000000', $this->member->fresh()->total_investment);
            $this->assertSame('inactive', $this->member->fresh()->status);
            $this->assertSame(0, DB::table('financial_ledger')->where('entry_type', 'package_activation')->count());
        } finally {
            DB::statement('ALTER TABLE member_security_audits DROP CONSTRAINT activation_audit_test');
        }
    }

    public function test_email_changed_during_password_reset_lookup_cannot_issue_old_email_otp(): void
    {
        config(['mail.default' => 'smtp']);
        DB::table('leobot_password_security_settings')->where('id', 1)->update(['enabled' => true]);
        Mail::shouldReceive('raw')->never();
        $changed = false;
        $email = $this->member->email;
        DB::listen(function ($query) use (&$changed, $email): void {
            if (! $changed && str_contains(strtolower($query->sql), 'lower(email)') && in_array(strtolower($email), $query->bindings, true)) {
                $changed = true;
                Accounts::email($this->admin, $this->member->id, $email, 'changed-during-reset@example.test');
            }
        });
        $this->post(route('password.otp.send'), ['login' => $email])->assertRedirect(route('password.otp.form'))->assertSessionHasNoErrors();
        $this->assertTrue($changed);
        $this->assertSame('changed-during-reset@example.test', $this->member->fresh()->email);
        $this->assertDatabaseCount('password_reset_otps', 0);
    }
}
