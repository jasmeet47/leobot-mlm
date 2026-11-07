<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthCaptchaOtpUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_captcha_is_displayed_and_enforced_before_authentication(): void
    {
        config(['leobot_auth.captcha_enabled' => true]);
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password' => Hash::make('Password123!')]);
        $this->get('/login')->assertOk()->assertSee('lumi-login-captcha');
        $this->postJson('/login', ['username' => $user->username, 'password' => 'Password123!'])->assertUnprocessable()->assertJsonValidationErrors('login_captcha');
        $this->assertGuest();
        $this->get('/login');
        $challenge = session('lumiflowon.login_captcha');
        $this->post('/login', ['username' => $user->username, 'password' => 'Password123!', 'login_captcha' => $challenge['answer']])->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('lumiflowon.login_captcha'));
    }

    public function test_expired_or_wrong_captcha_does_not_authenticate(): void
    {
        config(['leobot_auth.captcha_enabled' => true]);
        $this->withSession(['lumiflowon.login_captcha' => ['answer' => '9', 'expires_at' => time() - 1]])->postJson('/login', ['username' => 'unknown', 'password' => 'wrong', 'login_captcha' => '9'])->assertJsonValidationErrors('login_captcha');
        $this->withSession(['lumiflowon.login_captcha' => ['answer' => '9', 'expires_at' => time() + 60]])->postJson('/login', ['username' => 'unknown', 'password' => 'wrong', 'login_captcha' => '1'])->assertJsonValidationErrors('login_captcha');
        $this->assertGuest();
    }

    public function test_registration_otp_controls_are_visible_and_real_verification_returns_success(): void
    {
        DB::table('registration_email_otp_settings')->where('id', 1)->update(['is_enabled' => true]);
        $this->get('/join')->assertOk()->assertSee('Send Email OTP')->assertSee('lumi-otp-code')->assertSee('Verify OTP')->assertSee('#22c55e', false);
        $token = str_repeat('c', 64);
        DB::table('registration_email_otp_challenges')->insert(['email' => 'signup@example.test', 'challenge_token_hash' => hash('sha256', $token), 'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(5), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/registration-email-otp/verify', ['email' => 'signup@example.test', 'otp' => '000000', 'challenge_token' => $token])->assertUnprocessable();
        $this->postJson('/registration-email-otp/verify', ['email' => 'signup@example.test', 'otp' => '123456', 'challenge_token' => $token])->assertOk()->assertJson(['success' => true, 'message' => 'OTP verified successfully.']);
        $this->assertNotNull(DB::table('registration_email_otp_challenges')->where('email', 'signup@example.test')->value('verified_at'));
    }
}
