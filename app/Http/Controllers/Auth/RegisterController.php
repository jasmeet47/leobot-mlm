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
            $sponsor = User::where('username', $referralCode)->first();

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
        $request->validate([
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

        $user = DB::transaction(function () use ($request) {

            /*
             * Lock the sponsor row while registration is being created.
             *
             * This prevents race conditions when multiple users
             * register under the same sponsor at the same time.
             */
            $sponsor = User::where('username', $request->sponsor_id)
                ->lockForUpdate()
                ->first();

            if (!$sponsor) {
                throw new \RuntimeException(
                    'INVALID_SPONSOR'
                );
            }

            /*
             * Generate a unique member username.
             */
            do {
                $username = 'TX' . random_int(100000, 999999);
            } while (
                User::where('username', $username)->exists()
            );

            /*
             * Create the member.
             *
             * sponsor_user_id is the permanent database relationship.
             * sponsor_id is retained for backward compatibility.
             */
            return User::create([
                'username' => $username,

                'sponsor_id' => $sponsor->username,

                'sponsor_user_id' => $sponsor->id,

                'name' => $request->name,

                'email' => $request->email,

                'phone' => $request->mobile,

                'password' => Hash::make($request->password),

                'security_pin' => null,

                'status' => 'active',
            ]);
        });

        $myReferralLink = url(
            '/join?ref=' . $user->username
        );

        Auth::login($user);

        $request->session()->regenerate();

        return back()->with([
            'success_reg' => true,

            'new_username' => $user->username,

            'ref_link' => $myReferralLink,
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
            'username' => $credentials['username'],
            'password' => $credentials['password'],
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
                    'Invalid username or password.'
            ])
            ->withInput(
                $request->only('username')
            );
    }
}