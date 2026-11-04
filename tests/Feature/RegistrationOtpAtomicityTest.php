<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RegistrationOtpAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_account_creation_does_not_consume_verified_otp(): void
    {
        $sponsor = User::factory()->create();
        DB::table('registration_email_otp_settings')->where('id', 1)->update(['is_enabled' => true]);
        $token = str_repeat('a', 64);
        $email = 'new-member@example.test';
        $id = DB::table('registration_email_otp_challenges')->insertGetId(['email' => $email,
            'challenge_token_hash' => hash('sha256', $token), 'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5), 'verified_at' => now(), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::statement('ALTER TABLE users ADD CONSTRAINT test_reject_signup CHECK (false) NOT VALID');
        try {
            try {
                $this->withoutExceptionHandling()->postJson('/api/register', ['sponsor_id' => $sponsor->username,
                    'name' => 'New member', 'email' => $email, 'mobile' => '9000000001',
                    'password' => 'Password12345!', 'password_confirmation' => 'Password12345!', 'challenge_token' => $token]);
                $this->fail('Account insert failure expected');
            } catch (QueryException) {
                $this->assertDatabaseHas('registration_email_otp_challenges', ['id' => $id, 'consumed_at' => null]);
                $this->assertDatabaseMissing('users', ['email' => $email]);
            }
        } finally {
            DB::statement('ALTER TABLE users DROP CONSTRAINT test_reject_signup');
        }
    }
}
