<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OptionalMemberPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_optional_login_opens_dashboard_without_changing_pin(): void
    {
        config(['leobot_auth.member_pin_required' => false, 'leobot_auth.captcha_enabled' => false]);
        $user = User::factory()->create(['role' => 'member', 'status' => 'inactive', 'security_pin' => null, 'password' => Hash::make('OptionalPin123!')]);
        $this->post('/login', ['username' => $user->username, 'password' => 'OptionalPin123!'])->assertRedirect(route('member.dashboard'));
        $this->get('/member/dashboard')->assertOk();
        $this->assertNull($user->fresh()->security_pin);
    }

    public function test_optional_pin_preserves_role_and_blocked_account_checks(): void
    {
        config(['leobot_auth.member_pin_required' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'security_pin' => null]);
        $this->actingAs($admin)->get('/member/dashboard')->assertForbidden();
        $blocked = User::factory()->create(['role' => 'member', 'status' => 'active', 'is_blocked' => true, 'security_pin' => null]);
        $this->actingAs($blocked)->get('/member/dashboard')->assertForbidden();
    }

    public function test_pin_flash_is_hidden_but_other_success_messages_remain(): void
    {
        config(['leobot_auth.member_pin_required' => false]);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => null]);
        $this->actingAs($member)->withSession(['success' => 'Security PIN saved successfully.'])->get('/member/dashboard')->assertOk()->assertDontSee('Security PIN saved successfully.');
        $this->withSession(['success' => 'Your profile was updated.'])->get('/member/dashboard')->assertOk()->assertSee('Your profile was updated.');
    }

    public function test_pin_requirement_can_still_be_enabled(): void
    {
        config(['leobot_auth.member_pin_required' => true]);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'security_pin' => null]);
        $this->actingAs($member)->get('/member/dashboard')->assertRedirect(route('security-pin.form'));
    }
}
