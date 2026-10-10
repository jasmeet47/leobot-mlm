<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MemberRegistrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function sponsor(string $username = 'ADMIN'): User
    {
        return User::create([
            'username' => $username,
            'sponsor_id' => null,
            'sponsor_user_id' => null,
            'name' => 'Test Sponsor',
            'email' => strtolower($username) . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('Password12345!'),
            'security_pin' => null,
            'status' => 'active',
        ]);
    }

    private function data(string $sponsor = 'ADMIN', string $email = 'new-member@example.test'): array
    {
        return [
            'sponsor_id' => $sponsor,
            'name' => 'New Member',
            'email' => $email,
            'mobile' => '9876543210',
            'password' => 'A-Secure-Password123!',
            'password_confirmation' => 'A-Secure-Password123!',
        ];
    }

    public function test_valid_sponsor_api_returns_only_public_identity(): void
    {
        $this->sponsor();
        $this->postJson('/api/verify-sponsor', ['sponsor_id' => 'ADMIN'])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'sponsor_id' => 'ADMIN',
                'sponsor_name' => 'Test Sponsor',
            ])
            ->assertDontSee('Password12345');
    }

    public function test_unknown_sponsor_api_returns_invalid_without_creating_members(): void
    {
        $this->postJson('/api/verify-sponsor', ['sponsor_id' => 'NO-SUCH-SPONSOR'])
            ->assertOk()->assertJson(['valid' => false]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_sponsor_api_rejects_missing_input(): void
    {
        $this->postJson('/api/verify-sponsor', [])
            ->assertUnprocessable()->assertJsonValidationErrors('sponsor_id');
    }

    public function test_web_registration_links_sponsor_and_starts_with_zero_wallets(): void
    {
        $sponsor = $this->sponsor();
        $response = $this->from('/join')->post('/register', $this->data());
        $response->assertRedirect('/join')->assertSessionHasNoErrors()
            ->assertSessionHas('success_reg', true)
            ->assertSessionHas('new_username');

        $member = User::where('email', 'new-member@example.test')->firstOrFail();
        $this->assertMatchesRegularExpression('/\ATX\d{6}\z/', $member->username);
        $this->assertSame('ADMIN', $member->sponsor_id);
        $this->assertSame((int) $sponsor->id, (int) $member->sponsor_user_id);
        $this->assertSame('inactive', $member->status);
        $this->assertNull($member->security_pin);
        $this->assertTrue(Hash::check('A-Secure-Password123!', $member->password));
        $this->assertAuthenticatedAs($member);
        $this->assertZeroFinances($member->id);
    }

    public function test_api_registration_returns_json_without_logging_user_in(): void
    {
        $this->sponsor();
        $response = $this->postJson('/api/register', $this->data());
        $response->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('user.status', 'inactive')
            ->assertJsonPath('user.sponsor_id', 'ADMIN');
        $this->assertMatchesRegularExpression('/\ATX\d{6}\z/', $response->json('user.username'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $memberId = (int) User::where('email', 'new-member@example.test')->value('id');
        $this->assertZeroFinances($memberId);
    }

    public function test_invalid_sponsor_rejected_on_web_and_api(): void
    {
        $this->from('/join')->post('/register', $this->data('FAKE'))
            ->assertRedirect('/join')->assertSessionHasErrors('sponsor_id');
        $this->postJson('/api/register', $this->data('FAKE'))
            ->assertUnprocessable()->assertJsonValidationErrors('sponsor_id');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_and_password_mismatch_are_rejected(): void
    {
        $this->sponsor();
        $this->postJson('/api/register', $this->data())->assertCreated();
        $this->postJson('/api/register', $this->data())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $bad = $this->data('ADMIN', 'different@example.test');
        $bad['password_confirmation'] = 'wrong';
        $this->postJson('/api/register', $bad)
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_multiple_members_have_distinct_usernames(): void
    {
        $this->sponsor();
        $this->postJson('/api/register', $this->data())->assertCreated();
        $this->postJson('/api/register', $this->data('ADMIN', 'another@example.test'))
            ->assertCreated();
        $names = User::where('username', 'like', 'TX%')->pluck('username')->all();
        $this->assertCount(2, $names);
        $this->assertCount(2, array_unique($names));
    }

    public function test_public_registration_is_rate_limited(): void
    {
        $this->sponsor();
        // Invalid payloads also consume the limit, preventing validation spam.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/register', [])->assertStatus(429);
        $this->assertDatabaseCount('users', 1);
    }

    private function assertZeroFinances(int $memberId): void
    {
        $row = DB::table('users')->where('id', $memberId)->first();
        foreach (['activation_balance', 'available_balance', 'total_investment', 'lifetime_income'] as $field) {
            $this->assertSame(0, bccomp((string) $row->{$field}, '0', 8), $field . ' must start at zero');
        }
        $this->assertDatabaseCount('financial_ledger', 0);
    }
}
