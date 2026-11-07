<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
        $request->session()->forget('password_reset_verified');
        $data = $request->validate(['login' => ['required', 'string', 'max:255']]);
        $identifier = trim($data['login']);
        $matches = DB::table('users')->where('username', $identifier)->orWhereRaw('LOWER(email) = ?', [Str::lower($identifier)])->limit(2)->get();
        $user = $matches->count() === 1 ? $matches[0] : null;
        $message = 'If this account is eligible, a password reset code has been sent to the registered email. Please check your inbox.';
        if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL) ||
            ($user->is_blocked || $user->deleted_at !== null || ! in_array($user->status, ['active', 'inactive'], true))) {
            return redirect()->route('password.otp.form')->with('reset_login', $identifier)->with('status', $message);
        }
        $otp = (string) random_int(100000, 999999);
        $key = 'leobot-password-otp-send:'.hash('sha256', (string) $user->id);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return redirect()->route('password.otp.form')->with('reset_login', $identifier)->with('status', $message);
        }
        RateLimiter::hit($key, 60);
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
                'Your LUMIFLOWON password reset code is '.$otp.'. It expires in 5 minutes. Never share it.',
                function ($mail) use ($user) {
                    $mail->to($user->email)->subject('LUMIFLOWON password reset code');
                }
            );
        } catch (\Throwable $e) {
            // Do not invalidate a newer OTP created by a concurrent request.
            DB::table('password_reset_otps')
                ->where('user_id', $user->id)
                ->where('otp_hash', $otpHash)
                ->delete();
            report($e);

            return redirect()->route('password.otp.form')->with('reset_login', $identifier)->with('status', $message);
        }

        return redirect()->route('password.otp.form')->with('reset_login', $identifier)->with('status', $message);
    }

    public function resetForm()
    {
        abort_unless($this->enabled(), 404);

        return view('auth.password-otp-reset');
    }

    public function verify(Request $request)
    {
        $this->guard();
        $data = $request->validate(['login' => 'required|string|max:255', 'otp' => 'required|digits:6']);
        $matches = DB::table('users')->where('username', trim($data['login']))->orWhereRaw('LOWER(email)=?', [Str::lower(trim($data['login']))])->limit(2)->get();
        $user = $matches->count() === 1 ? $matches[0] : null;
        if (! $user || $user->is_blocked || $user->deleted_at || ! in_array($user->status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }
        $digest = DB::transaction(function () use ($user, $data) {
            $row = DB::table('password_reset_otps')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $row || $row->attempts >= 5 || now()->gte($row->expires_at)) {
                return null;
            }
            if (! Hash::check($data['otp'], $row->otp_hash)) {
                DB::table('password_reset_otps')->where('user_id', $user->id)->increment('attempts');

                return null;
            }

            return hash('sha256', $row->otp_hash);
        });
        if (! $digest) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }
        $request->session()->put('password_reset_verified', ['user_id' => $user->id, 'digest' => $digest, 'login' => $data['login']]);

        return redirect()->route('password.otp.form')->with('status', 'OTP verified successfully. Enter your new password.');
    }

    public function reset(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'otp' => ['nullable', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $identifier = trim($data['login']);
        $matches = DB::table('users')->where('username', $identifier)->orWhereRaw('LOWER(email) = ?', [Str::lower($identifier)])->limit(2)->get();
        $user = $matches->count() === 1 ? $matches[0] : null;
        if (! $user || ($user->is_blocked || $user->deleted_at !== null || ! in_array($user->status, ['active', 'inactive'], true))) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }
        $verified = $request->session()->get('password_reset_verified');
        $result = DB::transaction(function () use ($user, $data, $verified) {
            $fresh = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->is_blocked || $fresh->deleted_at || ! in_array($fresh->status, ['active', 'inactive'], true)) {
                throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
            }
            $record = DB::table('password_reset_otps')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $record || $record->attempts >= 5 || now()->greaterThanOrEqualTo($record->expires_at)) {
                throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
            }
            $sessionVerified = is_array($verified) && (int) $verified['user_id'] === (int) $user->id && hash_equals($verified['digest'], hash('sha256', $record->otp_hash));
            if (! $sessionVerified && ! Hash::check($data['otp'] ?? '', $record->otp_hash)) {
                DB::table('password_reset_otps')->where('user_id', $user->id)->increment('attempts');

                return false;
            }
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
                'member_login_version' => DB::raw('member_login_version + 1'),
                'updated_at' => now(),
            ]);
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)
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
        if (! $result) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired code.']);
        }
        $request->session()->forget('password_reset_verified');

        return redirect()->route('login.form')->with('status', 'Your password successfully changed. Please sign in with your new password.');
    }
}
