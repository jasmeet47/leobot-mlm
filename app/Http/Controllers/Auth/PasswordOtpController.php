<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordOtpController extends Controller
{
    private function enabled(): bool
    {
        return Schema::hasTable('leobot_password_security_settings')
            && Schema::hasTable('password_reset_otps')
            && Schema::hasTable('password_reset_security_events')
            && Schema::hasColumn('users', 'member_login_version')
            && (bool) DB::table('leobot_password_security_settings')->where('id', 1)->value('enabled');
    }

    private function guard(): void
    {
        abort_unless($this->enabled(), 404);
        abort_if(in_array(config('mail.default'), ['log', 'array'], true), 503, 'Email delivery unavailable.');
    }

    public function requestForm()
    {
        abort_unless($this->enabled(), 404);
        return view('auth.password-otp-request');
    }

    public function send(Request $request)
    {
        $this->guard();
        $data = $request->validate(['login' => ['required', 'string', 'max:255']]);
        $identifier = trim($data['login']);
        $user = DB::table('users')->where('username', $identifier)
            ->orWhereRaw('LOWER(email) = ?', [Str::lower($identifier)])->first();
        $message = 'If this account is eligible, a password reset code has been sent to the registered email. Please check your inbox.';
        if (!$user || !filter_var($user->email, FILTER_VALIDATE_EMAIL) ||
            (isset($user->is_blocked) && $user->is_blocked)) {
            return back()->with('status', $message);
        }
        $otp = (string) random_int(100000, 999999);
        $key = 'leobot-password-otp-send:'.hash('sha256', (string) $user->id);
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('status', $message);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        $now = now();
        $otpHash = Hash::make($otp);

        // Persist the replacement code before attempting delivery.
        // Previous code is invalidated as soon as the new code is saved.
        DB::transaction(function () use ($user, $otpHash, $now) {
            DB::table('password_reset_otps')->updateOrInsert(['user_id' => $user->id], [
                'otp_hash' => $otpHash, 'attempts' => 0,
                'expires_at' => $now->copy()->addMinutes(5), 'sent_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('password_reset_security_events')->insert([
                'user_id' => $user->id, 'event' => 'otp_sent', 'created_at' => $now,
            ]);
        });
        try {
            Mail::raw(
                'Your LeoBot password reset code is '.$otp.'. It expires in 5 minutes. Never share it.',
                function ($mail) use ($user) {
                    $mail->to($user->email)->subject('LeoBot password reset code');
                }
            );
        } catch (\Throwable $e) {
            // Do not invalidate a newer OTP created by a concurrent request.
            DB::table('password_reset_otps')
                ->where('user_id', $user->id)
                ->where('otp_hash', $otpHash)
                ->delete();
            report($e);
            return back()->with('status', $message);
        }
        return redirect()->route('password.otp.form')->with('status', $message);
    }

    public function resetForm()
    {
        abort_unless($this->enabled(), 404);
        return view('auth.password-otp-reset');
    }

    public function reset(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $identifier = trim($data['login']);
        $user = DB::table('users')->where('username', $identifier)
            ->orWhereRaw('LOWER(email) = ?', [Str::lower($identifier)])->first();
        if (!$user || (isset($user->is_blocked) && $user->is_blocked)) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }
        $result = DB::transaction(function () use ($user, $data) {
            $record = DB::table('password_reset_otps')->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$record || $record->attempts >= 5 || now()->greaterThanOrEqualTo($record->expires_at)) {
                throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
            }
            if (!Hash::check($data['otp'], $record->otp_hash)) {
                DB::table('password_reset_otps')->where('user_id', $user->id)->increment('attempts');
                return false;
            }
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
                'member_login_version' => DB::raw('member_login_version + 1'),
                'updated_at' => now(),
            ]);
            DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)
                ->where('tokenable_id', $user->id)->delete();
            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            DB::table('password_reset_otps')->where('user_id', $user->id)->delete();
            DB::table('password_reset_security_events')->insert([
                'user_id' => $user->id, 'event' => 'password_reset', 'created_at' => now(),
            ]);
            return true;
        });
        if (!$result) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }
        return redirect()->route('register.form')->with('status', 'Your password has been successfully reset. Please sign in with your new password.');
    }
}
