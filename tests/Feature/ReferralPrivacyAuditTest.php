<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralPrivacyAuditTest extends TestCase
{
    use RefreshDatabase;

    private function validSettings(): array
    {
        return [
            'phone_visibility' => 'masked',
            'email_visibility' => 'masked',
            'show_investment_amount' => '1',
            'show_package_status' => '1',
        ];
    }

    private function assertSettingsUnchanged(): void
    {
        $settings = DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->first();

        $this->assertNotNull($settings);
        $this->assertSame('off', $settings->phone_visibility);
        $this->assertSame('off', $settings->email_visibility);
        $this->assertFalse((bool) $settings->show_investment_amount);
        $this->assertFalse((bool) $settings->show_package_status);

        $this->assertDatabaseCount(
            'referral_privacy_setting_audits',
            0
        );
    }

    public function test_admin_change_creates_audit_with_old_and_new_settings(): void
    {
        $admin = User::factory()->create([
            'username' => 'ADMIN',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(
                '/admin/referral-privacy/update',
                $this->validSettings()
            )
            ->assertRedirect(route('admin.referral-privacy.index'));

        $audits = DB::table('referral_privacy_setting_audits')->get();

        $this->assertCount(1, $audits);

        $audit = $audits->first();

        $this->assertEquals($admin->id, $audit->admin_user_id);
        $this->assertSame('ADMIN', $audit->admin_username);

        $old = json_decode(
            $audit->old_settings,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $new = json_decode(
            $audit->new_settings,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('off', $old['phone_visibility']);
        $this->assertSame('off', $old['email_visibility']);
        $this->assertFalse($old['show_investment_amount']);
        $this->assertFalse($old['show_package_status']);

        $this->assertSame('masked', $new['phone_visibility']);
        $this->assertSame('masked', $new['email_visibility']);
        $this->assertTrue($new['show_investment_amount']);
        $this->assertTrue($new['show_package_status']);
    }

    public function test_settings_rollback_when_audit_insert_fails(): void
    {
        $admin = User::factory()->create([
            'username' => 'ADMIN',
            'status' => 'active',
            'role' => 'admin',
        ]);

        // Simulate audit storage failure in isolated test database.
        Schema::drop('referral_privacy_setting_audits');

        $this->actingAs($admin)
            ->post(
                '/admin/referral-privacy/update',
                $this->validSettings()
            )
            ->assertStatus(500);

        $settings = DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->first();

        $this->assertSame('off', $settings->phone_visibility);
        $this->assertSame('off', $settings->email_visibility);
        $this->assertFalse((bool) $settings->show_investment_amount);
        $this->assertFalse((bool) $settings->show_package_status);
    }

    public function test_member_with_admin_username_cannot_view_settings(): void
    {
        $member = User::factory()->create([
            'username' => 'ADMIN',
            'status' => 'active',
            'role' => 'member',
        ]);

        $this->actingAs($member)
            ->get('/admin/referral-privacy')
            ->assertForbidden();
    }

    public function test_member_with_admin_username_cannot_update_settings(): void
    {
        $member = User::factory()->create([
            'username' => 'ADMIN',
            'status' => 'active',
            'role' => 'member',
        ]);

        $this->actingAs($member)
            ->post(
                '/admin/referral-privacy/update',
                $this->validSettings()
            )
            ->assertForbidden();

        $this->assertSettingsUnchanged();
    }

    public function test_regular_member_cannot_update_settings(): void
    {
        $member = User::factory()->create([
            'username' => 'PRIVACY_MEMBER',
            'status' => 'active',
            'role' => 'member',
        ]);

        $this->actingAs($member)
            ->post(
                '/admin/referral-privacy/update',
                $this->validSettings()
            )
            ->assertForbidden();

        $this->assertSettingsUnchanged();
    }

    public function test_active_admin_with_different_username_can_update_settings(): void
    {
        $admin = User::factory()->create([
            'username' => 'PRIVACY_MANAGER',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(
                '/admin/referral-privacy/update',
                $this->validSettings()
            )
            ->assertRedirect(route('admin.referral-privacy.index'));

        $this->assertDatabaseHas('referral_privacy_settings', [
            'id' => 1,
            'phone_visibility' => 'masked',
            'email_visibility' => 'masked',
        ]);

        $this->assertDatabaseHas('referral_privacy_setting_audits', [
            'admin_user_id' => $admin->id,
            'admin_username' => 'PRIVACY_MANAGER',
        ]);

        $this->assertDatabaseCount(
            'referral_privacy_setting_audits',
            1
        );
    }

    public function test_active_super_admin_can_update_settings(): void
    {
        $admin = User::factory()->create([
            'username' => 'PRIVACY_SUPER',
            'status' => 'active',
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin)
            ->post(
                '/admin/referral-privacy/update',
                $this->validSettings()
            )
            ->assertRedirect(route('admin.referral-privacy.index'));

        $this->assertDatabaseHas('referral_privacy_setting_audits', [
            'admin_user_id' => $admin->id,
            'admin_username' => 'PRIVACY_SUPER',
        ]);
    }

    public function test_inactive_admin_cannot_update_settings(): void
    {
        $admin = User::factory()->create([
            'username' => 'PRIVACY_INACTIVE',
            'status' => 'inactive',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(
                '/admin/referral-privacy/update',
                $this->validSettings()
            )
            ->assertForbidden();

        $this->assertSettingsUnchanged();
    }
}
