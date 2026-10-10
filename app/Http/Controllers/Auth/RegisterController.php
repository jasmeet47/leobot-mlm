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

        return view('auth-page', compact('referralCode', 'sponsorName'));
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

        $sponsor = User::query()->where('username', $data['sponsor_id'])
            ->first(['username', 'name']);

        if (!$sponsor) {
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
     * POST /register: ordinary web form (session login + existing success flash).
     * POST /api/register: stateless JSON, NO web session and NO access token.
     */
    public function register(Request $request)
    {
        self::limitPublicRequest($request, 'register', 10);

        $validated = $request->validate([
            'sponsor_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = null;

        // Each retry uses a NEW transaction: a PostgreSQL unique-constraint
        // violation aborts its transaction and cannot be recovered within it.
        for ($attempt = 0; $attempt < self::MAX_USERNAME_TRIES; $attempt++) {
            $candidate = 'TX' . random_int(100000, 999999);

            try {
                $user = DB::transaction(function () use ($validated, $candidate) {
                    $sponsor = User::where('username', $validated['sponsor_id'])
                        ->lockForUpdate()->first();

                    if (!$sponsor) {
                        throw ValidationException::withMessages([
                            'sponsor_id' => 'Invalid Sponsor ID. Please enter a valid Sponsor ID.',
                        ]);
                    }

                    return User::create([
                        'username' => $candidate,
                        'sponsor_id' => $sponsor->username,
                        'sponsor_user_id' => $sponsor->getKey(),
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'phone' => $validated['mobile'],
                        'password' => Hash::make($validated['password']),
                        'security_pin' => null,
                        'status' => 'inactive',
                    ]);
                }, 3);

                break;
            } catch (QueryException $exception) {
                if (!self::isUsernameCollision($exception)) {
                    throw $exception;
                }
            }
        }

        if (!$user) {
            throw new RuntimeException('Could not allocate a unique member username. Please retry.');
        }

        $referralLink = url('/join?ref=' . $user->username);

        if ($request->is('api/*')) {
            // API registrations must not call Auth::login() or session()->regenerate().
            // Activation remains required; no bearer token is issued by registration.
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
        $request->session()->regenerate();

        return back()->with([
            'success_reg' => true,
            'new_username' => $user->username,
            'ref_link' => $referralLink,
        ]);
    }

    /**
     * Only retry the users.username constraint. A duplicate email, foreign
     * key error or any other DB exception must NEVER be silently retried.
     */
    private static function isUsernameCollision(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        $isUniqueViolation = $sqlState === '23505'      // PostgreSQL
            || ($sqlState === '23000' && $driverCode === '1062') // MySQL
            || ($sqlState === '23000' && $driverCode === '19'); // SQLite

        return $isUniqueViolation
            && (str_contains($message, 'users_username_unique')
                || str_contains($message, 'users.username'));
    }

    /**
     * Same per-IP guard for both the web and API endpoints. The limit counts
     * failed attempts as well as successful attempts, preventing easy abuse.
     */
    private static function limitPublicRequest(Request $request, string $action, int $limit): void
    {
        $key = 'leobot:' . $action . ':' . hash('sha256', $request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            abort(429, 'Too many requests. Please try again later.');
        }

        RateLimiter::hit($key, 60);
    }

    // Existing web login behavior retained.
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginSuccess = Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ]);

        if ($loginSuccess) {
            $request->session()->regenerate();

            if (strtoupper((string) $request->user()->username) === 'ADMIN') {
                return redirect()->intended('/admin/level-config');
            }

            // Explicit destination: a pre-saved intended URL cannot bypass PIN setup.
            return \App\Http\Middleware\EnsureMemberPin::hasConfiguredPin($request->user())
                ? redirect()->route('member.dashboard')
                : redirect()->route('security-pin.form');
        }

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ])->withInput($request->only('username'));
    }
}
