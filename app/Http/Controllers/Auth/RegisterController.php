<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\RegistrationEmailOtpService;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Public member registration, sponsor lookup and existing web member login.
 * No activation, financial transfer, commission or wallet credit takes place here.
 */
class RegisterController extends Controller
{
    private const MAX_USERNAME_TRIES = 12;

    public function showRegistrationForm(Request $request)
    {
        $referralCode = $request->query('ref');
        $sponsorName = null;

        if (is_string($referralCode) && $referralCode !== '') {
            $sponsor = User::where('username', $referralCode)->first();

            if ($sponsor) {
                $sponsorName = $sponsor->name;
            } else {
                $referralCode = null;
            }
        } else {
            $referralCode = null;
        }

        $registrationOtpEnabled = app(RegistrationEmailOtpService::class)->enabled();
        $captchaA = random_int(2, 9);
        $captchaB = random_int(1, 9);
        $request->session()->put('lumiflowon_registration_captcha', $captchaA + $captchaB);
        $loginCaptchaA = random_int(2, 9);
        $loginCaptchaB = random_int(1, 9);
        $request->session()->put('lumiflowon_login_captcha', $loginCaptchaA + $loginCaptchaB);
        return view('auth-page', compact('referralCode', 'sponsorName', 'registrationOtpEnabled', 'captchaA', 'captchaB', 'loginCaptchaA', 'loginCaptchaB'));
    }

    /**
     * POST /api/verify-sponsor
     * Accepts sponsor_id and exposes only the sponsor's public name/username.
     */
    public function verifySponsor(Request $request)
    {
        self::limitPublicRequest($request, 'verify-sponsor', 30);

        $data = $request->validate([
            'sponsor_id' => ['required', 'string', 'max:255'],
        ]);

        $sponsor = User::query()
            ->where('username', $data['sponsor_id'])
            ->first(['username', 'name', 'is_blocked', 'deleted_at']);

        if (!$sponsor || $sponsor->is_blocked || $sponsor->deleted_at !== null) {
            return response()->json([
                'valid' => false,
                'message' => 'Sponsor ID not found.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'sponsor_id' => $sponsor->username,
            'sponsor_name' => $sponsor->name,
        ]);
    }

    /**
     * POST /register: ordinary web form.
     * POST /api/register: stateless JSON, no session and no access token.
     */
    public function register(Request $request)
    {
        self::limitPublicRequest($request, 'register', 10);

        // Web-only session challenge. API clients remain on the existing separate flow.
        if (!$request->is('api/*')) {
            $expected = $request->session()->get('lumiflowon_registration_captcha');
            $answer = $request->input('registration_captcha');
            if (!is_int($expected) || !is_string($answer) || !ctype_digit($answer)
                || !hash_equals((string) $expected, $answer)) {
                throw ValidationException::withMessages([
                    'registration_captcha' => 'Incorrect security answer. Refresh the page and try again.',
                ]);
            }
            $request->session()->forget('lumiflowon_registration_captcha');
        }

        // Normalize email before uniqueness validation and OTP verification.
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $validated = $request->validate([
            'sponsor_id' => ['required', 'string', 'max:255'],
            'beta_invite_code' => ['required', 'string', 'regex:/\A[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}\z/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'challenge_token' => ['nullable', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        // Fail closed when admin enables registration email OTP.
        $registrationOtp = app(RegistrationEmailOtpService::class);
        $requireRegistrationOtp = $registrationOtp->enabled();
        if ($requireRegistrationOtp && empty($validated['challenge_token'])) {
            throw ValidationException::withMessages(['challenge_token' => 'Email verification is required.']);
        }

        // BETA_INVITE_ONLY_V1: both web and API registration require a single-use invite.
        $validated['beta_invite_hash'] = hash('sha256', $validated['beta_invite_code']);
        unset($validated['beta_invite_code']);

        $user = null;

        // Each retry uses a new transaction.
        for ($attempt = 0; $attempt < self::MAX_USERNAME_TRIES; $attempt++) {
            $candidate = 'TX' . random_int(100000, 999999);

            try {
                $user = DB::transaction(function () use (
                    $validated,
                    $candidate,
                    $requireRegistrationOtp
                ) {
                    // Lock the invitation inside the same transaction as member creation.
                    $invite = DB::table('beta_registration_invites')
                        ->where('code_hash', $validated['beta_invite_hash'])
                        ->lockForUpdate()->first();
                    if (!$invite || $invite->used_at !== null) {
                        throw ValidationException::withMessages([
                            'beta_invite_code' => 'Invalid or already used invitation code.',
                        ]);
                    }

                    $sponsor = User::where(
                        'username',
                        $validated['sponsor_id']
                    )->lockForUpdate()->first();

                    if (!$sponsor || $sponsor->is_blocked || $sponsor->deleted_at !== null) {
                        throw ValidationException::withMessages([
                            'sponsor_id' => 'Invalid Sponsor ID. Please enter a valid Sponsor ID.',
                        ]);
                    }

                    if ($requireRegistrationOtp) {
                        app(RegistrationEmailOtpService::class)->consumeVerified($validated['email'], $validated['challenge_token']);
                    }

                    $newMember = User::create([
                        'username' => $candidate,
                        'sponsor_id' => $sponsor->username,
                        'sponsor_user_id' => $sponsor->getKey(),
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'phone' => $validated['mobile'],
                        'password' => Hash::make(
                            $validated['password']
                        ),
                        'security_pin' => null,
                        'status' => 'inactive',
                    ]);

                    DB::table('beta_registration_invites')
                        ->where('id', $invite->id)
                        ->update(['used_at' => now(), 'used_by_user_id' => $newMember->id]);

                    \App\Services\TeamCounterService::synchronize(
                        (int) $newMember->id
                    );

                    return $newMember;
                }, 3);

                break;
            } catch (QueryException $exception) {
                if (!self::isUsernameCollision($exception)) {
                    throw $exception;
                }
            }
        }

        if (!$user) {
            throw new RuntimeException(
                'Could not allocate a unique member username. Please retry.'
            );
        }

        $referralLink = url('/join?ref=' . $user->username);

        if ($request->is('api/*')) {
            // API registration issues no session or bearer token.
            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Activation is required.',
                'user' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'status' => $user->status,
                    'sponsor_id' => $user->sponsor_id,
                ],
                'ref_link' => $referralLink,
            ], 201);
        }

        Auth::login($user);
        $request->session()->put('member_login_version', (int) $user->member_login_version);
        $request->session()->regenerate();

        return back()->with([
            'success_reg' => true,
            'new_username' => $user->username,
            'ref_link' => $referralLink,
        ]);
    }

    /**
     * Retry only users.username unique-constraint collisions.
     */
    private static function isUsernameCollision(
        QueryException $exception
    ): bool {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        $isUniqueViolation = $sqlState === '23505'
            || ($sqlState === '23000' && $driverCode === '1062')
            || ($sqlState === '23000' && $driverCode === '19');

        return $isUniqueViolation
            && (
                str_contains($message, 'users_username_unique')
                || str_contains($message, 'users.username')
            );
    }

    /**
     * Rate-limit public registration and sponsor verification.
     */
    private static function limitPublicRequest(
        Request $request,
        string $action,
        int $limit
    ): void {
        $key = 'leobot:'
            . $action
            . ':'
            . hash('sha256', $request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            abort(429, 'Too many requests. Please try again later.');
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Web login with role-based admin redirect.
     */
    public function login(Request $request)
    {
        // LUMIFLOWON_LOGIN_CAPTCHA_V1: one-time session challenge before Auth::attempt.
        $expected = $request->session()->get('lumiflowon_login_captcha');
        $answer = $request->input('login_captcha');
        $request->session()->forget('lumiflowon_login_captcha');
        if (!is_int($expected) || !is_string($answer) || !ctype_digit($answer)
            || !hash_equals((string) $expected, $answer)) {
            throw ValidationException::withMessages([
                'login_captcha' => 'Incorrect login security answer. Reload the page and try again.',
            ]);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // LEOBOT_LOGIN_TIMING_V1 - temporary diagnostic, no personal data
        $lbLoginStart = hrtime(true);

        $loginSuccess = Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ]);

        \Illuminate\Support\Facades\Log::info('leobot.login.auth_timing', [
            'auth_ms' => round((hrtime(true) - $lbLoginStart) / 1000000, 2),
            'success' => $loginSuccess,
        ]);

        if ($loginSuccess) {
            $user = $request->user();
            if ($user->deleted_at !== null || $user->is_blocked || !in_array($user->status, ['active', 'inactive'], true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'username' => 'Invalid username or password.',
                ])->withInput($request->only('username'));
            }

            $request->session()->regenerate();
            $request->session()->put('member_login_version', (int) $user->member_login_version);

            // Only active admin/super_admin roles receive admin redirect.
            // A username of ADMIN alone grants no admin privileges.
            if (
                $user->status === 'active'
                && in_array(
                    $user->role,
                    ['admin', 'super_admin'],
                    true
                )
            ) {
                return redirect()->route('admin.dashboard');
            }

            // Members must complete PIN setup before dashboard access.
            // A saved intended URL cannot bypass PIN setup.
            return \App\Http\Middleware\EnsureMemberPin::hasConfiguredPin(
                $user
            )
                ? redirect()->route('member.dashboard')
                : redirect()->route('security-pin.form');
        }

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ])->withInput($request->only('username'));
    }
}
