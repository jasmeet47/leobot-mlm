<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StakingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class BetaPublicUiTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'is_blocked' => false,
            'security_pin' => Hash::make('123456'), 'member_login_version' => 0]);
    }

    public function test_beta_admin_navigation_and_feature_pages_render_with_current_safe_routes(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin);
        foreach (['/admin/dashboard', '/admin/staking', '/admin/withdrawals', '/admin/staking/profit',
            '/admin/logo-settings', '/admin/registration-email-otp-settings', '/admin/magic-matrix',
            '/admin/members', '/admin/quick-links', '/admin/password-otp-settings'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/admin/dashboard')->assertSee('lf-sidebar')->assertSee('Staking Control')->assertSee('Logo Management');
    }

    public function test_admin_can_turn_staking_on_and_off_and_server_denies_new_stakes_when_off(): void
    {
        config(['leobot.staking_enabled' => true, 'leobot.production_financial_approval' => true]);
        $admin = $this->account('admin');
        $this->actingAs($admin)->from('/admin/staking')->post('/admin/staking/toggle', ['enabled' => 1])->assertRedirect('/admin/staking');
        $this->get('/admin/staking')->assertSee('Turn Staking OFF');
        $this->postJson('/admin/staking/toggle', ['enabled' => 0])->assertOk()->assertJson(['enabled' => false]);
        $this->get('/admin/staking')->assertSee('Turn Staking ON');
        $member = $this->account('member');
        try {
            app(StakingService::class)->create($member->id, '1', 'disabled_stake_request_1');
            $this->fail('Disabled staking accepted a request.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Staking disabled', $e->getMessage());
        }
        $this->assertDatabaseCount('staking_positions', 0);
        $this->assertDatabaseCount('financial_ledger', 0);
        $this->assertDatabaseCount('staking_control_audits', 2);
    }

    public function test_member_cannot_view_or_change_administrator_controls(): void
    {
        $this->actingAs($this->account('member'))->get('/admin/staking')->assertForbidden();
        $this->postJson('/admin/staking/toggle', ['enabled' => 1])->assertForbidden();
        $this->assertDatabaseCount('staking_control_audits', 0);
    }

    public function test_beta_member_detail_pages_remain_scoped_and_do_not_write_money(): void
    {
        $member = $this->account('member');
        $other = $this->account('member');
        DB::table('financial_ledger')->insert(['user_id' => $other->id, 'entry_type' => 'private_other', 'wallet_type' => 'available_balance',
            'amount' => '11', 'balance_before' => '0', 'balance_after' => '11', 'transaction_key' => 'private-other-key', 'status' => 'completed', 'description' => 'OTHER_PRIVATE_LEDGER']);
        $this->actingAs($member);
        foreach (['/member/dashboard', '/member/profile', '/member/staking', '/member/deposit-transfer', '/member/withdrawals',
            '/member/page/wallet-history', '/member/page/direct-referrals', '/member/page/magic-team'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('OTHER_PRIVATE_LEDGER');
        }
        $this->get('/member/page/anything')->assertNotFound();
        $this->assertDatabaseCount('financial_ledger', 1);
        $this->assertDatabaseCount('staking_positions', 0);
    }

    public function test_password_change_requires_old_password_and_revokes_old_sessions_without_changing_money(): void
    {
        $member = $this->account('member');
        $member->forceFill(['password' => Hash::make('OldPassword123!'), 'available_balance' => '17'])->save();
        $member->createToken('old-token');
        $this->actingAs($member)->from('/member/profile')->post('/member/profile/password', [
            'current_password' => 'wrong', 'password' => 'NewPassword456!', 'password_confirmation' => 'NewPassword456!',
        ])->assertSessionHasErrors('current_password');
        $this->post('/member/profile/password', ['current_password' => 'OldPassword123!', 'password' => 'NewPassword456!', 'password_confirmation' => 'NewPassword456!'])
            ->assertSessionHasNoErrors()->assertSessionHas('member_login_version', 1);
        $this->assertTrue(Hash::check('NewPassword456!', $member->fresh()->password));
        $this->assertSame('17.00000000', $member->fresh()->available_balance);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_matrix_display_setting_is_audited_without_enabling_financial_switches(): void
    {
        $this->actingAs($this->account('admin'))->post('/admin/magic-matrix/display-levels', ['visible_levels' => 7])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('magic_matrix_settings', ['id' => 1, 'visible_levels' => 7]);
        $this->assertDatabaseHas('magic_matrix_settings_audits', ['action' => 'display_levels_update']);
        $this->assertDatabaseCount('financial_ledger', 0);
        $this->assertFalse((bool) DB::table('staking_controls')->where('id', 1)->value('enabled'));
    }
}
