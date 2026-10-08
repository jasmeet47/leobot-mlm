<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegistrationForm(Request $request)
    {
        $referralCode = $request->query('ref');
        $sponsorName = null;

        if (!empty($referralCode)) {
            $sponsor = User::where(
                'username',
                $referralCode
            )->first();

            if ($sponsor) {
                $sponsorName = $sponsor->name;
            } else {
                $referralCode = null;
            }
        }

        return view('auth-page', compact(
            'referralCode',
            'sponsorName'
        ));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'sponsor_id' => [
                'required',
                'string',
                'max:255',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'mobile' => [
                'required',
                'string',
                'max:20',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $sponsor = User::where(
            'username',
            $validated['sponsor_id']
        )->first();

        if (!$sponsor) {
            return back()
                ->withErrors([
                    'sponsor_id' =>
                        'Invalid Sponsor ID. Please enter a valid Sponsor ID.',
                ])
                ->withInput();
        }

        $user = DB::transaction(function () use (
            $validated,
            $sponsor
        ) {
            $lockedSponsor = User::where(
                'id',
                $sponsor->id
            )
                ->lockForUpdate()
                ->first();

            if (!$lockedSponsor) {
                throw new \RuntimeException(
                    'Sponsor account could not be locked.'
                );
            }

            do {
                $username =
                    'TX' . random_int(100000, 999999);

            } while (
                User::where(
                    'username',
                    $username
                )->exists()
            );

            return User::create([
                'username' => $username,

                'sponsor_id' =>
                    $lockedSponsor->username,

                'sponsor_user_id' =>
                    $lockedSponsor->id,

                'name' =>
                    $validated['name'],

                'email' =>
                    $validated['email'],

                'phone' =>
                    $validated['mobile'],

                'password' =>
                    Hash::make(
                        $validated['password']
                    ),

                'security_pin' => null,

                // Activation is required before becoming active.
                'status' => 'inactive',
            ]);
        });

        $myReferralLink = url(
            '/join?ref=' . $user->username
        );

        Auth::login($user);

        $request->session()->regenerate();

        return back()->with([
            'success_reg' => true,

            'new_username' =>
                $user->username,

            'ref_link' =>
                $myReferralLink,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        $loginSuccess = Auth::attempt([
            'username' =>
                $credentials['username'],

            'password' =>
                $credentials['password'],
        ]);

        if ($loginSuccess) {
            $request->session()->regenerate();

            if (
                strtoupper(
                    $credentials['username']
                ) === 'ADMIN'
            ) {
                return redirect()->intended(
                    '/admin/level-config'
                );
            }

            return redirect()->intended('/join');
        }

        return back()
            ->withErrors([
                'username' =>
                    'Invalid username or password.',
            ])
            ->withInput(
                $request->only('username')
            );
    }
}
