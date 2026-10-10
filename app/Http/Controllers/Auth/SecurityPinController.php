<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class SecurityPinController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Set or Change Security PIN
    |--------------------------------------------------------------------------
    |
    | Requirements:
    | - Member must be logged in.
    | - Account password must be correct.
    | - PIN must contain exactly 6 digits.
    | - PIN confirmation must match.
    | - PIN is stored as a secure hash.
    | - Repeated attempts are rate limited.
    |
    | This method does not transfer money.
    */

    public function update(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user !== null,
            401,
            'Authentication required.'
        );

        /*
         * Rate limit by authenticated user.
         * Maximum 5 attempts in 5 minutes.
         */
        $rateLimitKey = 'security-pin:update:' . $user->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()->withErrors([
                'security_pin' =>
                    'Too many attempts. Please try again in '
                    . $seconds . ' seconds.',
            ]);
        }

        /*
         * Validate input without flashing
         * passwords or PINs back into session.
         */
        $validator = Validator::make(
            $request->all(),
            [
                'current_password' => [
                    'required',
                    'string',
                ],

                'security_pin' => [
                    'required',
                    'string',
                    'regex:/\A[0-9]{6}\z/',
                    'confirmed',
                ],
            ],
            [
                'security_pin.regex' =>
                    'Security PIN must contain exactly 6 digits.',

                'security_pin.confirmed' =>
                    'Security PIN confirmation does not match.',
            ]
        );

        if ($validator->fails()) {
            RateLimiter::hit($rateLimitKey, 300);

            return back()->withErrors($validator);
        }

        $validated = $validator->validated();

        /*
         * Count password verification attempts.
         */
        RateLimiter::hit($rateLimitKey, 300);

        /*
         * Verify account password securely.
         * Never compare passwords as plain text.
         */
        if (
            !Hash::check(
                $validated['current_password'],
                $user->password
            )
        ) {
            return back()->withErrors([
                'current_password' =>
                    'Incorrect account password.',
            ]);
        }

        /*
         * Hash the PIN before saving.
         *
         * A six-digit PIN must never be
         * stored as plain text.
         */
        $hashedPin = Hash::make(
            $validated['security_pin']
        );

        /*
         * Update only the logged-in member.
         * Do not accept user_id from the request.
         */
        $user->forceFill([
            'security_pin' => $hashedPin,
        ])->save();

        /*
         * Clear failed-attempt counter
         * after a successful PIN change.
         */
        RateLimiter::clear($rateLimitKey);

        // Preserve the existing Admin flow; members proceed to their dashboard.
        if (strtoupper((string) $user->username) === 'ADMIN') {
            return back()->with('success', 'Security PIN saved successfully.');
        }

        return redirect()->route('member.dashboard')->with(
            'success',
            'Security PIN saved successfully.'
        );
    }
}
