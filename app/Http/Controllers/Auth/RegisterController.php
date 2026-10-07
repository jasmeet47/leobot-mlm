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
    /*
    |--------------------------------------------------------------------------
    | SHOW LOGIN / REGISTER PAGE
    |--------------------------------------------------------------------------
    */

    public function showRegistrationForm(Request $request)
    {
        /*
        | Get referral code
        |
        | Example:
        | /join?ref=TX123456
        */

        $referralCode = $request->query('ref');

        $sponsorName = null;


        /*
        |--------------------------------------------------------------------------
        | CHECK SPONSOR
        |--------------------------------------------------------------------------
        */

        if (!empty($referralCode)) {

            $sponsor = DB::table('users')
                ->where('username', $referralCode)
                ->first();


            if ($sponsor) {

                $sponsorName = $sponsor->name;

            } else {

                /*
                | Invalid referral code
                */

                $referralCode = null;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LOAD AUTH PAGE
        |--------------------------------------------------------------------------
        */

        return view(
            'auth-page',
            compact(
                'referralCode',
                'sponsorName'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REGISTER USER
    |--------------------------------------------------------------------------
    */

    public function register(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'sponsor_id' => [
                'required',
                'string',
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


        /*
        |--------------------------------------------------------------------------
        | FIND SPONSOR
        |--------------------------------------------------------------------------
        */

        $sponsor = User::where(
            'username',
            $request->sponsor_id
        )->first();


        if (!$sponsor) {

            return back()
                ->withErrors([
                    'sponsor_id' =>
                        'Invalid Sponsor ID. Please enter a valid Sponsor ID.'
                ])
                ->withInput();
        }


        /*
        |--------------------------------------------------------------------------
        | GENERATE USERNAME
        |--------------------------------------------------------------------------
        |
        | Example:
        | TX123456
        |
        */

        do {

            $username = 'TX' . random_int(
                100000,
                999999
            );

        } while (
            User::where(
                'username',
                $username
            )->exists()
        );


        /*
        |--------------------------------------------------------------------------
        | CREATE USER
        |--------------------------------------------------------------------------
        */

        $user = User::create([

            'username' => $username,

            'sponsor_id' => $sponsor->username,

            'name' => $request->name,

            'email' => $request->email,

            'phone' => $request->mobile,

            'password' => Hash::make(
                $request->password
            ),

            'status' => 'active',

        ]);


        /*
        |--------------------------------------------------------------------------
        | CREATE REFERRAL LINK
        |--------------------------------------------------------------------------
        */

        $myReferralLink = url(
            '/join?ref=' . $username
        );


        /*
        |--------------------------------------------------------------------------
        | AUTO LOGIN
        |--------------------------------------------------------------------------
        */

        Auth::login($user);

        $request->session()->regenerate();


        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return back()->with([

            'success_reg' => true,

            'new_username' => $username,

            'ref_link' => $myReferralLink,

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | LOGIN ATTEMPT
        |--------------------------------------------------------------------------
        */

        $loginSuccess = Auth::attempt([

            'username' => $credentials['username'],

            'password' => $credentials['password'],

        ]);


        /*
        |--------------------------------------------------------------------------
        | LOGIN SUCCESS
        |--------------------------------------------------------------------------
        */

        if ($loginSuccess) {

            $request->session()->regenerate();


            /*
            |--------------------------------------------------------------------------
            | ADMIN
            |--------------------------------------------------------------------------
            */

            if (
                strtoupper(
                    $credentials['username']
                ) === 'ADMIN'
            ) {

                return redirect()->intended(
                    '/admin/level-config'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | NORMAL USER
            |--------------------------------------------------------------------------
            */

            return redirect()->intended(
                '/join'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN FAILED
        |--------------------------------------------------------------------------
        */

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