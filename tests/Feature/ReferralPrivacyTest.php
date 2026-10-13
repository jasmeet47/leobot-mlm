<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferralPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_settings_are_off_by_default(): void
    {
        $settings = DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->first();

        $this->assertNotNull($settings);
        $this->assertSame('off', $settings->phone_visibility);
        $this->assertSame('off', $settings->email_visibility);
        $this->assertFalse((bool) $settings->show_investment_amount);
        $this->assertFalse((bool) $settings->show_package_status);
    }

    public function test_guest_cannot_access_admin_privacy_settings(): void
    {
        $response = $this->get('/admin/referral-privacy');

        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_regular_member_cannot_access_admin_privacy_settings(): void
    {
        $member = User::factory()->create([
            'username' => 'MEMBER100',
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->get('/admin/referral-privacy')
            ->assertForbidden();
    }

    public function test_regular_member_cannot_change_privacy_settings(): void
    {
        $member = User::factory()->create([
            'username' => 'MEMBER101',
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->post('/admin/referral-privacy/update', [
                'phone_visibility' => 'full',
                'email_visibility' => 'full',
                'show_investment_amount' => '1',
                'show_package_status' => '1',
            ])
            ->assertForbidden();

        $settings = DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->first();

        $this->assertSame('off', $settings->phone_visibility);
        $this->assertSame('off', $settings->email_visibility);
    }

    public function test_active_admin_can_view_privacy_settings(): void
    {
        $admin = User::factory()->create([
            'username' => 'ADMIN',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/referral-privacy')
            ->assertOk()
            ->assertSee('Referral Privacy Settings');
    }

    public function test_active_admin_can_update_privacy_settings(): void
    {
        $admin = User::factory()->create([
            'username' => 'ADMIN',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post('/admin/referral-privacy/update', [
                'phone_visibility' => 'masked',
                'email_visibility' => 'masked',
                'show_investment_amount' => '1',
                'show_package_status' => '1',
            ])
            ->assertRedirect(route('admin.referral-privacy.index'));

        $settings = DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->first();

        $this->assertSame('masked', $settings->phone_visibility);
        $this->assertSame('masked', $settings->email_visibility);
        $this->assertTrue((bool) $settings->show_investment_amount);
        $this->assertTrue((bool) $settings->show_package_status);
    }

    public function test_invalid_visibility_values_are_rejected(): void
    {
        $admin = User::factory()->create([
            'username' => 'ADMIN',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post('/admin/referral-privacy/update', [
                'phone_visibility' => 'invalid',
                'email_visibility' => 'full',
                'show_investment_amount' => '1',
                'show_package_status' => '1',
            ])
            ->assertSessionHasErrors('phone_visibility');
    }
}
