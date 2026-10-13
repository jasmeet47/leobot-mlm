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

        $sponsor = User::query()
            ->where('username', $data['sponsor_id'])
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
     * POST /register: ordinary web form.
     * POST /api/register: stateless JSON, no session and no access token.
     */
    public function register(Request $request)
    {
        self::limitPublicRequest($request, 'register', 10);

        $validated = $request->validate([
            'sponsor_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $user = null;

        // Each retry uses a new transaction.
        for ($attempt = 0; $attempt < self::MAX_USERNAME_TRIES; $attempt++) {
            $candidate = 'TX' . random_int(100000, 999999);

            try {
                $user = DB::transaction(function () use (
                    $validated,
                    $candidate
                ) {
                    $sponsor = User::where(
                        'username',
                        $validated['sponsor_id']
                    )->lockForUpdate()->first();

                    if (!$sponsor) {
                        throw ValidationException::withMessages([
                            'sponsor_id' => 'Invalid Sponsor ID. Please enter a valid Sponsor ID.',
                        ]);
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

            $user = $request->user();

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
                return redirect('/admin/level-config');
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
