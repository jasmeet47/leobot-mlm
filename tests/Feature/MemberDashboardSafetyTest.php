<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberDashboardSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function member(?string $pin = '123456', string $status = 'inactive'): User
    {
        $unique = Str::lower(Str::random(16));
        $id = DB::table('users')->insertGetId([
            'username' => 'TX' . strtoupper(substr($unique, 0, 10)),
            'name' => 'Dashboard Test ' . $unique,
            'email' => $unique . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => $pin === null ? null : Hash::make($pin),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/member/dashboard')->assertRedirect('/join');
    }

    public function test_member_without_pin_is_sent_to_pin_setup(): void
    {
        $member = $this->member(null);
        $this->actingAs($member)->get('/member/dashboard')
            ->assertRedirect(route('security-pin.form'));
    }

    public function test_member_with_hashed_pin_can_open_own_dashboard(): void
    {
        $member = $this->member();
        $other = $this->member();
        DB::table('users')->where('id', $other->id)
            ->update(['available_balance' => '98765.25000000']);

        $this->actingAs($member)->get('/member/dashboard')
            ->assertOk()
            ->assertViewIs('member.dashboard')
            ->assertSee($member->name)
            ->assertSee('51-Level Team Breakdown')
            ->assertSee('Level 51')
            ->assertSee('0.00000000')
            ->assertDontSee($other->name)
            ->assertDontSee('98765.25000000');
    }

    public function test_legacy_plaintext_pin_is_not_accepted_for_dashboard(): void
    {
        $member = $this->member();
        $member->forceFill(['security_pin' => '123456'])->save();

        $this->actingAs($member)->get('/member/dashboard')
            ->assertRedirect(route('security-pin.form'));
    }

    public function test_guest_cannot_access_dashboard_api(): void
    {
        $this->getJson('/api/dashboard-counters')->assertUnauthorized();
    }

    public function test_member_without_pin_cannot_bypass_through_api_or_debug_route(): void
    {
        $member = $this->member(null);
        Sanctum::actingAs($member);
        $this->getJson('/api/dashboard-counters')
            ->assertForbidden()
            ->assertJsonPath('code', 'security_pin_required');
        $this->get('/check-dashboard')
            ->assertRedirect(route('security-pin.form'));
    }

    public function test_api_returns_only_authenticated_members_counters(): void
    {
        $member = $this->member();
        Sanctum::actingAs($member);
        $this->getJson('/api/dashboard-counters')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_info.username', $member->username)
            ->assertJsonPath('data.overview_counters.total_active_team', 0);
    }

    public function test_member_with_pin_logs_in_directly_to_dashboard(): void
    {
        $member = $this->member();
        $this->post('/login', [
            'username' => $member->username,
            'password' => 'TestPassword123!',
        ])->assertRedirect(route('member.dashboard'));

        $this->assertAuthenticatedAs($member);
    }

    public function test_member_without_pin_logs_in_to_security_pin_setup(): void
    {
        $member = $this->member(null);
        $this->post('/login', [
            'username' => $member->username,
            'password' => 'TestPassword123!',
        ])->assertRedirect(route('security-pin.form'));
    }

    public function test_active_admin_still_logs_in_to_admin_settings(): void
    {
        $admin = $this->member(null, 'active');
        $admin->forceFill(['username' => 'ADMIN'])->save();

        $this->post('/login', [
            'username' => 'ADMIN',
            'password' => 'TestPassword123!',
        ])->assertRedirect('/admin/level-config');
    }

    public function test_admin_cannot_open_member_dashboard(): void
    {
        $admin = $this->member('654321', 'active');
        $admin->forceFill(['username' => 'ADMIN'])->save();

        $this->actingAs($admin)->get('/member/dashboard')->assertForbidden();
        $this->getJson('/api/dashboard-counters')->assertForbidden();
    }

    public function test_pin_setup_redirects_to_dashboard_without_transferring_money(): void
    {
        $member = $this->member(null);
        $this->actingAs($member)->post('/security-pin', [
            'current_password' => 'TestPassword123!',
            'security_pin' => '012345',
            'security_pin_confirmation' => '012345',
        ])->assertRedirect(route('member.dashboard'));

        $saved = $member->fresh();
        $this->assertTrue(Hash::check('012345', (string) $saved->security_pin));
        $this->assertSame('0.00000000', bcadd((string) $saved->available_balance, '0', 8));
        $this->assertSame('0.00000000', bcadd((string) $saved->lifetime_income, '0', 8));
    }

    public function test_logout_clears_session(): void
    {
        $member = $this->member();
        $this->actingAs($member)->post('/logout')
            ->assertRedirect(route('register.form'));
        $this->assertGuest();
    }
}
