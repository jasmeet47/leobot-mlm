<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DirectReferralsTest extends TestCase
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
            && ($cfg['database'] ?? '') === 'lumiflowon_final_regression_20261024'
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
            || $identity->database_name !== 'lumiflowon_final_regression_20261024'
            || (int) $identity->server_port !== 5433) {
            throw new \RuntimeException(
                'SAFETY STOP: Database identity mismatch.'
            );
        }

        $this->leobotOriginalRefreshDatabase();
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'role' => 'member',
            'security_pin' => Hash::make('123456'),
        ], $attributes));
    }

    private function privacy(array $settings): void
    {
        DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->update($settings);
    }

    public function test_contact_consent_form_defaults_to_off(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Referral Contact Privacy')
            ->assertSee('Save Privacy Choices')
            ->assertSee('name="phone_consent"', false)
            ->assertSee('name="email_consent"', false)
            ->assertDontSee('checked', false);
    }

    public function test_contact_consent_form_shows_saved_choices(): void
    {
        $member = $this->member();

        DB::table('referral_contact_consents')->insert([
            'user_id' => $member->id,
            'phone_consent' => true,
            'email_consent' => false,
            'phone_consented_at' => now(),
            'email_consented_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($member)
            ->get(route('member.dashboard'))
            ->assertOk();

        $response->assertSee('name="phone_consent"', false);
        $response->assertSee('name="email_consent"', false);

        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="checkbox"[^>]*name="phone_consent"[^>]*checked/s',
            $html
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*type="checkbox"[^>]*name="email_consent"[^>]*checked/s',
            $html
        );
    }

    public function test_member_sees_only_own_direct_referrals(): void
    {
        $sponsor = $this->member();
        $otherSponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'name' => 'Own Direct Member',
        ]);

        $this->member([
            'sponsor_user_id' => $otherSponsor->id,
            'name' => 'Other Sponsor Member',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Own Direct Member')
            ->assertDontSee('Other Sponsor Member');
    }

    public function test_second_level_member_is_not_shown(): void
    {
        $sponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'name' => 'First Level Member',
        ]);

        $this->member([
            'sponsor_user_id' => $direct->id,
            'name' => 'Second Level Member',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('First Level Member')
            ->assertDontSee('Second Level Member');
    }

    public function test_phone_and_email_are_hidden_by_default(): void
    {
        $sponsor = $this->member();

        $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'private.member@example.com',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('9876543210')
            ->assertDontSee('private.member@example.com');
    }

    public function test_masked_contact_does_not_reveal_full_values(): void
    {
        $this->privacy([
            'phone_visibility' => 'masked',
            'email_visibility' => 'masked',
        ]);

        $sponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'private.member@example.com',
        ]);

        DB::table('referral_contact_consents')->insert([
            'user_id' => $direct->id,
            'phone_consent' => true,
            'email_consent' => true,
            'phone_consented_at' => now(),
            'email_consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('98XXXXXX10')
            ->assertSee('p***@example.com')
            ->assertDontSee('9876543210')
            ->assertDontSee('private.member@example.com');
    }

    public function test_masked_contact_requires_member_consent(): void
    {
        $this->privacy([
            'phone_visibility' => 'masked',
            'email_visibility' => 'masked',
        ]);

        $sponsor = $this->member();

        $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'private.member@example.com',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('98XXXXXX10')
            ->assertDontSee('p***@example.com')
            ->assertDontSee('9876543210')
            ->assertDontSee('private.member@example.com');
    }

    public function test_revoked_consent_hides_masked_contact(): void
    {
        $this->privacy([
            'phone_visibility' => 'masked',
            'email_visibility' => 'masked',
        ]);

        $sponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'private.member@example.com',
        ]);

        DB::table('referral_contact_consents')->insert([
            'user_id' => $direct->id,
            'phone_consent' => true,
            'email_consent' => true,
            'phone_consented_at' => now(),
            'email_consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('98XXXXXX10')
            ->assertSee('p***@example.com');

        DB::table('referral_contact_consents')
            ->where('user_id', $direct->id)
            ->update([
                'phone_consent' => false,
                'email_consent' => false,
                'phone_consented_at' => null,
                'email_consented_at' => null,
                'updated_at' => now(),
            ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('98XXXXXX10')
            ->assertDontSee('p***@example.com')
            ->assertDontSee('9876543210')
            ->assertDontSee('private.member@example.com');
    }

    public function test_full_setting_cannot_bypass_missing_consent(): void
    {
        $this->privacy([
            'phone_visibility' => 'full',
            'email_visibility' => 'full',
        ]);

        $sponsor = $this->member();

        $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'private.member@example.com',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('9876543210')
            ->assertDontSee('private.member@example.com');
    }

    public function test_full_contact_requires_admin_setting_and_member_consent(): void
    {
        $this->privacy([
            'phone_visibility' => 'full',
            'email_visibility' => 'full',
        ]);

        $sponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'consenting.member@example.com',
        ]);

        DB::table('referral_contact_consents')->insert([
            'user_id' => $direct->id,
            'phone_consent' => true,
            'email_consent' => true,
            'phone_consented_at' => now(),
            'email_consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('9876543210')
            ->assertSee('consenting.member@example.com');
    }

    public function test_revoked_consent_hides_full_contact(): void
    {
        $this->privacy([
            'phone_visibility' => 'full',
            'email_visibility' => 'full',
        ]);

        $sponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'revoke.test@example.com',
        ]);

        DB::table('referral_contact_consents')->insert([
            'user_id' => $direct->id,
            'phone_consent' => true,
            'email_consent' => true,
            'phone_consented_at' => now(),
            'email_consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('9876543210')
            ->assertSee('revoke.test@example.com');

        DB::table('referral_contact_consents')
            ->where('user_id', $direct->id)
            ->update([
                'phone_consent' => false,
                'email_consent' => false,
                'phone_consented_at' => null,
                'email_consented_at' => null,
                'updated_at' => now(),
            ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('9876543210')
            ->assertDontSee('revoke.test@example.com');
    }

    public function test_phone_consent_does_not_grant_email_visibility(): void
    {
        $this->privacy([
            'phone_visibility' => 'full',
            'email_visibility' => 'full',
        ]);

        $sponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'private.email@example.com',
        ]);

        DB::table('referral_contact_consents')->insert([
            'user_id' => $direct->id,
            'phone_consent' => true,
            'email_consent' => false,
            'phone_consented_at' => now(),
            'email_consented_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('9876543210')
            ->assertDontSee('private.email@example.com');
    }

    public function test_other_sponsor_cannot_see_consented_contact(): void
    {
        $this->privacy([
            'phone_visibility' => 'full',
            'email_visibility' => 'full',
        ]);

        $realSponsor = $this->member();
        $otherSponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $realSponsor->id,
            'phone' => '9876543210',
            'email' => 'protected.referral@example.com',
        ]);

        DB::table('referral_contact_consents')->insert([
            'user_id' => $direct->id,
            'phone_consent' => true,
            'email_consent' => true,
            'phone_consented_at' => now(),
            'email_consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($realSponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('9876543210')
            ->assertSee('protected.referral@example.com');

        $this->actingAs($otherSponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('9876543210')
            ->assertDontSee('protected.referral@example.com')
            ->assertDontSee($direct->username);
    }

    public function test_admin_off_hides_contact_despite_member_consent(): void
    {
        $this->privacy([
            'phone_visibility' => 'off',
            'email_visibility' => 'off',
        ]);

        $sponsor = $this->member();

        $direct = $this->member([
            'sponsor_user_id' => $sponsor->id,
            'phone' => '9876543210',
            'email' => 'admin.off@example.com',
        ]);

        DB::table('referral_contact_consents')->insert([
            'user_id' => $direct->id,
            'phone_consent' => true,
            'email_consent' => true,
            'phone_consented_at' => now(),
            'email_consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee($direct->username)
            ->assertDontSee('9876543210')
            ->assertDontSee('admin.off@example.com');
    }

    public function test_investment_is_hidden_by_default(): void
    {
        $sponsor = $this->member();

        $this->member([
            'sponsor_user_id' => $sponsor->id,
            'total_investment' => '12345.12345678',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSee('12345.12345678');
    }

    public function test_admin_can_allow_investment_visibility(): void
    {
        $this->privacy([
            'show_investment_amount' => true,
        ]);

        $sponsor = $this->member();

        $this->member([
            'sponsor_user_id' => $sponsor->id,
            'total_investment' => '12345.12345678',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('12345.12345678');
    }

    public function test_package_status_is_not_guessed_from_account_status(): void
    {
        $this->privacy([
            'show_package_status' => true,
        ]);

        $sponsor = $this->member();

        $this->member([
            'sponsor_user_id' => $sponsor->id,
            'status' => 'active',
            'name' => 'Package Check Member',
        ]);

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Package Check Member')
            ->assertSee('No Package');
    }
}
