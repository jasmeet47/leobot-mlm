<?php

namespace Tests\Feature;

use App\Http\Controllers\Member\ReferralContactConsentController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReferralContactConsentTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private leobotOriginalRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        $name = config('database.default');
        $cfg = config('database.connections.' . $name, []);

        $safe = app()->environment('testing')
            && $name === 'pgsql'
            && ($cfg['host'] ?? '') === '127.0.0.1'
            && (string) ($cfg['port'] ?? '') === '5433'
            && ($cfg['database'] ?? '') === 'leobot_financial_disposable'
            && empty($cfg['url']);

        if (!$safe) {
            throw new \RuntimeException(
                'SAFETY STOP: Disposable PostgreSQL required.'
            );
        }

        $identity = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT current_database() AS database_name, inet_server_port() AS server_port'
        );

        if (!$identity
            || $identity->database_name !== 'leobot_financial_disposable'
            || (int) $identity->server_port !== 5433) {
            throw new \RuntimeException(
                'SAFETY STOP: Database identity mismatch.'
            );
        }

        $this->leobotOriginalRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Temporary test-only route. No production route is created.
        Route::middleware(['web', 'auth'])->post(
            '/test/referral-contact-consent',
            [ReferralContactConsentController::class, 'update']
        );
    }

    private function consentUrl(): string
    {
        return '/test/referral-contact-consent';
    }

    private function member(string $username = 'MEMBER100'): User
    {
        return User::factory()->create([
            'username' => $username,
            'status' => 'active',
            'role' => 'member',
        ]);
    }

    private function admin(string $username = 'ADMIN'): User
    {
        $admin = User::factory()->create([
            'username' => $username,
            'status' => 'active',
        ]);

        $admin->forceFill([
            'role' => 'admin',
        ])->save();

        return $admin;
    }

    public function test_guest_cannot_change_consent(): void
    {
        $this->post($this->consentUrl(), [
            'phone_consent' => '1',
            'email_consent' => '1',
        ])->assertRedirect();

        $this->assertSame(
            0,
            DB::table('referral_contact_consents')->count()
        );
    }

    public function test_member_can_grant_separate_consents(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => '1',
                'email_consent' => '0',
            ])
            ->assertSessionHas('success');

        $consent = DB::table('referral_contact_consents')
            ->where('user_id', $member->id)
            ->first();

        $this->assertTrue((bool) $consent->phone_consent);
        $this->assertFalse((bool) $consent->email_consent);
        $this->assertNotNull($consent->phone_consented_at);
        $this->assertNull($consent->email_consented_at);

        $audit = DB::table('referral_contact_consent_audits')
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($audit);

        $old = json_decode($audit->old_consents, true);
        $new = json_decode($audit->new_consents, true);

        $this->assertFalse($old['phone_consent']);
        $this->assertFalse($old['email_consent']);
        $this->assertTrue($new['phone_consent']);
        $this->assertFalse($new['email_consent']);
    }

    public function test_member_can_revoke_consent(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => '1',
                'email_consent' => '1',
            ]);

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => '0',
                'email_consent' => '0',
            ])
            ->assertSessionHas('success');

        $consent = DB::table('referral_contact_consents')
            ->where('user_id', $member->id)
            ->first();

        $this->assertFalse((bool) $consent->phone_consent);
        $this->assertFalse((bool) $consent->email_consent);
        $this->assertNull($consent->phone_consented_at);
        $this->assertNull($consent->email_consented_at);

        $this->assertSame(
            2,
            DB::table('referral_contact_consent_audits')
                ->where('user_id', $member->id)
                ->count()
        );
    }

    public function test_unchanged_consent_does_not_create_extra_audit(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => '1',
                'email_consent' => '0',
            ]);

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => '1',
                'email_consent' => '0',
            ]);

        $this->assertSame(
            1,
            DB::table('referral_contact_consent_audits')->count()
        );
    }

    public function test_member_cannot_change_another_members_consent(): void
    {
        $member = $this->member('MEMBER101');
        $other = $this->member('MEMBER102');

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => '1',
                'email_consent' => '1',
                'user_id' => $other->id,
            ]);

        $this->assertSame(
            0,
            DB::table('referral_contact_consents')
                ->where('user_id', $other->id)
                ->count()
        );
    }

    public function test_invalid_consent_values_are_rejected(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => 'invalid',
                'email_consent' => '1',
            ])
            ->assertSessionHasErrors('phone_consent');

        $this->assertSame(
            0,
            DB::table('referral_contact_consents')->count()
        );
    }

    public function test_audit_failure_rolls_back_consent(): void
    {
        $member = $this->member();

        // Isolated test database only.
        Schema::drop('referral_contact_consent_audits');

        $this->actingAs($member)
            ->post($this->consentUrl(), [
                'phone_consent' => '1',
                'email_consent' => '1',
            ])
            ->assertStatus(500);

        $consent = DB::table('referral_contact_consents')
            ->where('user_id', $member->id)
            ->first();

        $this->assertTrue(
            $consent === null ||
            (
                !(bool) $consent->phone_consent &&
                !(bool) $consent->email_consent
            )
        );
    }

    public function test_real_route_blocks_member_without_security_pin(): void
    {
        $member = $this->member('MEMBER_NO_PIN');

        $member->security_pin = null;
        $member->save();

        $this->actingAs($member)
            ->post(route('member.referral-contact-consent.update'), [
                'phone_consent' => '1',
                'email_consent' => '1',
            ])
            ->assertRedirect(route('security-pin.form'));

        $this->assertSame(
            0,
            DB::table('referral_contact_consents')
                ->where('user_id', $member->id)
                ->count()
        );

        $this->assertSame(
            0,
            DB::table('referral_contact_consent_audits')
                ->where('user_id', $member->id)
                ->count()
        );
    }

    public function test_real_route_blocks_admin_consent_update(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('member.referral-contact-consent.update'), [
                'phone_consent' => '1',
                'email_consent' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(
            0,
            DB::table('referral_contact_consents')->count()
        );

        $this->assertSame(
            0,
            DB::table('referral_contact_consent_audits')->count()
        );
    }

    public function test_real_route_allows_member_with_security_pin(): void
    {
        $member = $this->member('MEMBER_WITH_PIN');

        $member->security_pin = bcrypt('123456');
        $member->save();

        $this->actingAs($member)
            ->post(route('member.referral-contact-consent.update'), [
                'phone_consent' => '1',
                'email_consent' => '0',
            ])
            ->assertSessionHas('success');

        $consent = DB::table('referral_contact_consents')
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($consent);
        $this->assertTrue((bool) $consent->phone_consent);
        $this->assertFalse((bool) $consent->email_consent);

        $this->assertSame(
            1,
            DB::table('referral_contact_consent_audits')
                ->where('user_id', $member->id)
                ->count()
        );
    }

    public function test_admin_cannot_grant_member_consent(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post($this->consentUrl(), [
                'phone_consent' => '1',
                'email_consent' => '1',
            ])
            ->assertForbidden();
    }
}
