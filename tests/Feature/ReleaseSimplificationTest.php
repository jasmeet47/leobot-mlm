<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReleaseSimplificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_are_separate_server_rendered_forms(): void
    {
        $this->get('/login')->assertOk()->assertSee('action="'.route('login.submit').'"', false)->assertDontSee('action="'.route('register.submit').'"', false)->assertSee('Create Account');
        $this->get('/join')->assertOk()->assertSee('action="'.route('register.submit').'"', false)->assertDontSee('action="'.route('login.submit').'"', false);
    }

    public function test_auto_reference_weighted_distribution_date_range_and_double_submit(): void
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot.staking_profit_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        $a = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $ids = [];
        foreach (['10', '50', '100', '500', '1000'] as $i => $amount) {
            $u = User::factory()->create(['role' => 'member', 'status' => 'active']);
            $ids[] = $u->id;
            WalletService::credit($u->id, 'available_balance', $amount, 'test_fixture', 'auto_fixture_'.$i);
            app(StakingService::class)->create($u->id, $amount, 'auto_weight_stake_'.$i);
        }
        $d = ['amount' => '1660', 'period' => 'weekly', 'period_date' => '01/02/2026', 'period_end_date' => '05/02/2026', 'submission_token' => (string) Str::uuid(), 'realized_profit_confirmed' => '1'];
        $this->actingAs($a)->post(route('admin.income-distribution.submit'), $d)->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->post(route('admin.income-distribution.submit'), $d)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('income_distribution_requests', 1);
        $this->assertDatabaseCount('staking_profit_allocations', 5);
        $this->assertDatabaseHas('income_distribution_requests', ['period_date' => '2026-02-01', 'period_end_date' => '2026-02-05']);
        foreach ($ids as $i => $id) {
            $this->assertDatabaseHas('staking_profit_allocations', ['user_id' => $id, 'amount' => ['10', '50', '100', '500', '1000'][$i]]);
        }
        $this->assertDatabaseCount('profit_sharing_fundings', 0);
        $this->get(route('admin.staking.profit.index'))->assertOk()->assertDontSee('name="allocations"', false)->assertDontSee('name="evidence_reference"', false);
        $this->post(route('admin.income-distribution.submit'), array_replace($d, ['period_end_date' => '31/01/2026']))->assertSessionHasErrors('period_end_date');
    }

    public function test_password_otp_verification_then_password_confirmation_and_replay_denial(): void
    {
        config(['mail.default' => 'smtp']);
        DB::table('leobot_password_security_settings')->where('id', 1)->update(['enabled' => true]);
        $u = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $code = '';
        Mail::shouldReceive('raw')->once()->andReturnUsing(function ($text) use (&$code) {
            preg_match('/code is (\d{6})/', $text, $m);
            $code = $m[1];
        });
        $this->get(route('password.otp.request'))->assertOk();
        $this->post(route('password.otp.send'), ['login' => $u->email])->assertRedirect(route('password.otp.form'));
        $this->get(route('password.otp.form'))->assertOk()->assertSee('Verify OTP')->assertDontSee('name="password"', false);
        $this->post(route('password.otp.verify'), ['login' => $u->email, 'otp' => '000000'])->assertSessionHasErrors('otp');
        $this->post(route('password.otp.verify'), ['login' => $u->email, 'otp' => $code])->assertRedirect(route('password.otp.form'));
        $this->get(route('password.otp.form'))->assertOk()->assertSee('Set new password')->assertSee('Repeat password');
        $d = ['login' => $u->email, 'password' => 'ChangedOnlyTest123!', 'password_confirmation' => 'ChangedOnlyTest123!'];
        $this->post(route('password.otp.reset'), $d)->assertRedirect(route('login.form'))->assertSessionHas('status');
        $this->assertTrue(Hash::check($d['password'], $u->fresh()->password));
        $this->assertSame(1, (int) $u->fresh()->member_login_version);
        $this->assertDatabaseCount('password_reset_otps', 0);
        $this->post(route('password.otp.reset'), $d)->assertSessionHasErrors('otp');
    }

    public function test_magic_on_off_and_manual_ten_x_cap_do_not_pay_any_money(): void
    {
        config(['leobot_magic.magic_matrix_settings_write_enabled' => true]);
        $a = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($a)->post(route('admin.magic.switches'), ['global_enabled' => 1, 'enrollment_enabled' => 1, 'topup_enabled' => 1, 'settlement_enabled' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('magic_income_settings', ['id' => 1, 'magic_enabled' => true]);
        $this->post(route('admin.magic.policy'), ['minimum_usdt' => '50', 'earning_multiplier' => '10', 'frequency' => 'manual'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('magic_matrix_settings', ['id' => 1, 'earning_cap_multiplier' => 10, 'settlement_enabled' => true]);
        $this->assertDatabaseCount('financial_ledger', 0);
        $this->post(route('admin.magic.switches'), ['global_enabled' => 0, 'enrollment_enabled' => 0, 'topup_enabled' => 0, 'settlement_enabled' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('magic_income_settings', ['id' => 1, 'magic_enabled' => false]);
    }

    public function test_one_fifty_stake_enrolls_once_with_ten_x_cap_only_when_magic_controls_are_on(): void
    {
        config(['leobot.production_financial_approval' => true, 'leobot.staking_enabled' => true, 'leobot_magic.magic_matrix_enrollment_enabled' => true]);
        DB::table('staking_controls')->where('id', 1)->update(['enabled' => true]);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update(['enrollment_enabled' => true, 'minimum_tier_usdt' => '50', 'earning_cap_multiplier' => 10]);
        $u = User::factory()->create(['role' => 'member', 'status' => 'active']);
        WalletService::credit($u->id, 'available_balance', '100', 'test_fixture', 'enroll_fixture_fifty');
        app(StakingService::class)->create($u->id, '20', 'single_enroll_twenty');
        app(StakingService::class)->create($u->id, '30', 'single_enroll_thirty');
        $this->assertDatabaseCount('magic_matrix_cycles', 0);
        $id = app(StakingService::class)->create($u->id, '50', 'single_enroll_fifty');
        app(StakingService::class)->create($u->id, '50', 'single_enroll_fifty');
        $this->assertDatabaseHas('magic_matrix_cycles', ['user_id' => $u->id, 'staking_position_id' => $id, 'earning_cap_usdt' => '500', 'earning_multiplier' => 10]);
        $this->assertDatabaseCount('magic_matrix_cycles', 1);
    }
}
