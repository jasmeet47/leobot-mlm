<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccountAccessRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $username, string $role, string $status = 'active'): User
    {
        return User::factory()->create(['username' => $username, 'role' => $role, 'status' => $status, 'password' => Hash::make('SecureAccess123!'), 'security_pin' => null]);
    }

    public function test_inactive_company_member_homepage_offers_member_access_and_switch_account(): void
    {
        $company = $this->account('LUMIFLOWON', 'member', 'inactive');
        $this->actingAs($company)->get('/')->assertOk()->assertSee('Open Member Dashboard')->assertSee('Logout / Switch Account')->assertDontSee('Your account access is currently restricted.')->assertDontSee('Open Admin Dashboard');
        $this->get('/admin/dashboard')->assertForbidden()->assertSee('a Member account')->assertSee(route('admin.login'));
        $this->assertSame('member', $company->fresh()->role);
        $this->assertSame('inactive', $company->fresh()->status);
    }

    public function test_admin_login_accepts_lowercase_admin_identifier_and_sets_admin_session(): void
    {
        config(['leobot_auth.captcha_enabled' => false]);
        $admin = $this->account('ADMIN', 'admin');
        $this->get('/admin/login')->assertOk()->assertSee('Admin Login')->assertSee('name="portal" value="admin"', false);
        $this->post('/login', ['username' => 'admin', 'password' => 'SecureAccess123!', 'portal' => 'admin'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_company_credentials_cannot_sign_into_admin_portal_but_member_login_works(): void
    {
        config(['leobot_auth.captcha_enabled' => false]);
        $company = $this->account('LUMIFLOWON', 'member', 'inactive');
        $this->from('/admin/login')->post('/login', ['username' => 'lumiflowon', 'password' => 'SecureAccess123!', 'portal' => 'admin'])->assertRedirect('/admin/login')->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => 'lumiflowon', 'password' => 'SecureAccess123!'])->assertRedirect(route('security-pin.form'));
        $this->assertAuthenticatedAs($company);
    }

    public function test_signed_in_member_can_securely_switch_to_actual_admin_with_admin_credentials(): void
    {
        config(['leobot_auth.captcha_enabled' => false]);
        $company = $this->account('LUMIFLOWON', 'member', 'inactive');
        $admin = $this->account('ADMIN', 'admin');
        $this->actingAs($company)->get('/admin/login')->assertOk()->assertSee('Currently signed in as');
        $this->post('/login', ['username' => 'admin', 'password' => 'SecureAccess123!', 'portal' => 'admin'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_blocked_account_stays_restricted_and_duplicate_case_identifiers_are_rejected(): void
    {
        config(['leobot_auth.captcha_enabled' => false]);
        $blocked = $this->account('BLOCKED', 'member');
        $blocked->forceFill(['is_blocked' => true])->save();
        $this->actingAs($blocked)->get('/')->assertSee('currently restricted')->assertDontSee('Open Member Dashboard');
        auth()->logout();
        $this->account('ADMIN', 'admin');
        $this->account('admin', 'member');
        $this->post('/login', ['username' => 'admin', 'password' => 'SecureAccess123!', 'portal' => 'admin'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
