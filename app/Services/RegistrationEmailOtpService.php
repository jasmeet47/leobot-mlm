<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class RegistrationEmailOtpService
{
    public function enabled(): bool
    {
        if (!Schema::hasTable('registration_email_otp_settings')) {
            throw new \RuntimeException('Registration OTP infrastructure is not migrated.');
        }
        $setting = DB::table('registration_email_otp_settings')->where('id', 1)->first();
        if (!$setting) { throw new \RuntimeException('Registration OTP setting is missing.'); }
        return (bool) $setting->is_enabled;
    }

    // Caller must retain this opaque challenge token and send it during verify and signup.
    public function send(string $email, string $ip): string
    {
        $email = strtolower(trim($email));
        if (!$this->enabled()) { throw ValidationException::withMessages(['email' => 'Email OTP is disabled by admin.']); }
        $key = 'reg-otp-send:' . hash('sha256', $email . '|' . $ip);
        // LEOBOT_OTP_SECURITY_V2: reserve the attempt before mail/database work.
        if (RateLimiter::attempt($key, 1, fn () => true, 60) !== true) {
            throw ValidationException::withMessages(['email' => 'Please wait 60 seconds before requesting another OTP.']);
        }
        // Uniform outward response for registered and unregistered addresses.
        if (DB::table('users')->where('email', $email)->exists()) {
            return bin2hex(random_bytes(32));
        }
        $otp = (string) random_int(100000, 999999);
        $token = bin2hex(random_bytes(32));
        // PostgreSQL advisory transaction lock prevents concurrent active challenges per email.
        $id = DB::transaction(function () use ($email, $token, $otp) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['leobot-reg-otp:' . $email]);
            DB::table('registration_email_otp_challenges')->where('email', $email)->whereNull('consumed_at')->delete();
            return DB::table('registration_email_otp_challenges')->insertGetId([
            'email' => $email, 'challenge_token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinutes(5),
            'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        try {
            Mail::raw('Your LumiFlowOn registration OTP is ' . $otp . '. It expires in 5 minutes. Do not share it.', function ($message) use ($email) {
                $message->to($email)->subject('LumiFlowOn Registration Email Verification');
            });
        } catch (\Throwable $e) {
            DB::table('registration_email_otp_challenges')->where('id', $id)->delete();
            report($e);
            throw ValidationException::withMessages(['email' => 'OTP email could not be sent. Please try again later.']);
        }
        return $token;
    }

    public function verify(string $email, string $otp, string $token): void
    {
        $email = strtolower(trim($email));
        if (!$this->enabled()) { throw ValidationException::withMessages(['email' => 'Email OTP is disabled by admin.']); }
        $valid = DB::transaction(function () use ($email, $otp, $token) {
            $row = DB::table('registration_email_otp_challenges')
                ->where('email', $email)->where('challenge_token_hash', hash('sha256', $token))
                ->whereNull('consumed_at')->whereNull('verified_at')->lockForUpdate()->first();
            if (!$row || now()->greaterThanOrEqualTo($row->expires_at) || (int) $row->attempts >= 5) {
                throw ValidationException::withMessages(['otp' => 'OTP expired or too many attempts. Request a new code.']);
            }
            if (!Hash::check($otp, $row->otp_hash)) {
                DB::table('registration_email_otp_challenges')->where('id', $row->id)->increment('attempts');
                // Throw outside transaction to retain the failed attempt.
                return false;
            }
            DB::table('registration_email_otp_challenges')->where('id', $row->id)->update([
                'verified_at' => now(), 'updated_at' => now(),
            ]);
            return true;
        });
        if (!$valid) { throw ValidationException::withMessages(['otp' => 'Invalid OTP.']); }
    }

    // Call INSIDE the same DB transaction as member creation, with the original token.
    public function consumeVerified(string $email, string $token): void
    {
        $email = strtolower(trim($email));
        $updated = DB::table('registration_email_otp_challenges')->where('email', $email)
            ->where('challenge_token_hash', hash('sha256', $token))
            ->whereNotNull('verified_at')->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now(), 'updated_at' => now()]);
        if ($updated !== 1) { throw ValidationException::withMessages(['email' => 'Please verify your email OTP before registration.']); }
    }
}
