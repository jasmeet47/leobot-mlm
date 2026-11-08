<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompanyReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CompanyReferralSetupTest extends TestCase
{
    use RefreshDatabase;

    private function prepareCompany(): User
    {
        User::factory()->create(['username' => 'ADMIN', 'role' => 'admin', 'status' => 'active', 'sponsor_user_id' => null]);
        config(['leobot.company_referral_enabled' => true]);

        return CompanyReferralService::prepare('company@example.test');
    }

    private function registration(): array
    {
        return ['sponsor_id' => 'lumiflowon', 'name' => 'Public Member', 'email' => 'public@example.test', 'mobile' => '9876543210', 'password' => 'Secure-Password123!', 'password_confirmation' => 'Secure-Password123!'];
    }

    public function test_company_creation_is_idempotent_member_only_and_keeps_finances_zero(): void
    {
        $company = $this->prepareCompany();
        $this->assertSame($company->id, CompanyReferralService::prepare('company@example.test')->id);
        $this->assertSame('member', $company->fresh()->role);
        $this->assertSame('inactive', $company->status);
        $this->assertNull($company->security_pin);
        $this->assertSame('0.00000000', $company->fresh()->available_balance);
        $this->assertSame('0.00000000', $company->fresh()->activation_balance);
        $this->assertDatabaseCount('financial_ledger', 0);
        $this->assertDatabaseCount('magic_matrix_positions', 0);
        $this->assertDatabaseHas('member_team_states', ['user_id' => $company->id, 'sponsor_user_id' => $company->sponsor_user_id, 'is_counted' => true]);
        $this->actingAs($company)->get(route('admin.dashboard'))->assertForbidden();
        $this->expectExceptionMessage('ownership and sponsor review');
        CompanyReferralService::prepare('different-owner@example.test');
    }

    public function test_public_link_registration_and_two_level_genealogy_without_admin_exposure(): void
    {
        $company = $this->prepareCompany();
        DB::table('registration_email_otp_settings')->where('id', 1)->update(['is_enabled' => false]);
        $this->get('/join')->assertOk()->assertViewHas('referralCode', 'LUMIFLOWON');
        $this->get('/join?ref=LUMIFLOWON')->assertOk()->assertViewHas('sponsorName', 'LUMIFLOWON Official Company');
        $this->postJson('/api/verify-sponsor', ['sponsor_id' => 'ADMIN'])->assertJsonPath('valid', false)->assertDontSee('email');
        $this->postJson('/api/register', $this->registration())->assertCreated()->assertJsonPath('user.sponsor_id', 'LUMIFLOWON');
        $member = User::where('email', 'public@example.test')->firstOrFail();
        $this->assertSame($company->id, $member->sponsor_user_id);
        $this->assertSame('member', $member->role);
        $this->assertDatabaseHas('member_teams', ['user_id' => $company->id, 'level' => 1, 'inactive_count' => 1]);
        $this->assertDatabaseHas('member_teams', ['user_id' => $company->sponsor_user_id, 'level' => 2, 'inactive_count' => 1]);
        $this->postJson('/api/register', $this->registration())->assertUnprocessable();
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_otp_on_requires_verified_challenge_and_rejects_reuse(): void
    {
        $this->prepareCompany();
        DB::table('registration_email_otp_settings')->where('id', 1)->update(['is_enabled' => true]);
        $data = $this->registration();
        $this->postJson('/api/register', $data)->assertUnprocessable();
        $token = str_repeat('a', 64);
        DB::table('registration_email_otp_challenges')->insert(['email' => $data['email'], 'challenge_token_hash' => hash('sha256', $token), 'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(5), 'verified_at' => now(), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/api/register', $data + ['challenge_token' => $token])->assertCreated();
        $this->assertNotNull(DB::table('registration_email_otp_challenges')->value('consumed_at'));
        $data['email'] = 'different@example.test';
        $this->postJson('/api/register', $data + ['challenge_token' => $token])->assertUnprocessable();
    }

    public function test_invalid_admin_identity_and_existing_member_collision_do_not_change_accounts(): void
    {
        User::factory()->create(['username' => 'ADMIN', 'role' => 'member', 'status' => 'active']);
        $this->expectExceptionMessage('root ADMIN identity');
        CompanyReferralService::prepare('company@example.test');
    }

    public function test_existing_unrelated_company_username_is_not_taken_over(): void
    {
        User::factory()->create(['username' => 'ADMIN', 'role' => 'admin', 'status' => 'active']);
        $existing = User::factory()->create(['username' => 'LUMIFLOWON', 'name' => 'Unrelated Member']);
        try {
            CompanyReferralService::prepare('company@example.test');
            $this->fail('Existing account was claimed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('ownership', $error->getMessage());
        }
        $this->assertSame('Unrelated Member', $existing->fresh()->name);
        $this->assertDatabaseCount('financial_ledger', 0);
    }
}
