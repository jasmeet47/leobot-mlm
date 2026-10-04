<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * 1. Display Sign-Up / Login views and sniff out incoming referral parameters.
     */
    public function showRegistrationForm(Request $request)
    {
        $referralCode = $request->query('ref');
        $sponsorName = null;

        if (!empty($referralCode)) {
            $sponsor = DB::table('users')->where('username', $referralCode)->first();
            if ($sponsor) {
                $sponsorName = $sponsor->name;
            } else {
                $referralCode = null; // Clean out corrupted tokens
            }
        }

        return view('auth-page', compact('referralCode', 'sponsorName'));
    }

    /**
     * 2. Core Processing Hub for generating new Generation Tree structures.
     */
    public function register(Request $request)
    {
        $request->validate([
            'sponsor_id'   => 'required|string',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'mobile'       => 'required|string|max:20',
            'password'     => 'required|string|min:8|confirmed',
        ]);

        $sponsor = User::where('username', $request->sponsor_id)->first();
        if (!$sponsor) {
            return redirect()->back()->withErrors(['sponsor_id' => 'The chosen Sponsor ID is invalid or missing from our nodes.'])->withInput();
        }

        // Generate an immutable unique alphanumeric network address (e.g., TX983214)
        do {
            $username = 'TX' . rand(100000, 999999);
        } while (User::where('username', $username)->exists());

        // Write row elements directly to transactional ledger nodes
        $user = User::create([
            'username'     => $username,
            'sponsor_id'   => $sponsor->id,
            'name'         => $request->name,
            'email'        => $request->email,
            'phone'        => $request->mobile,
            'password'     => Hash::make($request->password),
            'status'       => 'active', // Active upon registration
        ]);

        // Construct unique permanent relative reference clip link
        $myReferralLink = 'https://leobot-mlm-1.onrender.com/join/?ref=ADMIN' . $username;

        // Automatically create session auth context to bypass login friction
        Auth::login($user);

        return redirect()->back()->with([
            'success_reg' => true,
            'new_username' => $username,
            'ref_link' => $myReferralLink
        ]);
    }

    /**
     * 3. Handle incoming secure sessions and safely route users based on permission flags.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Support logging in via unique node address
        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']])) {
            $request->session()->regenerate();
            
            if ($credentials['username'] === 'ADMIN') {
                return redirect()->intended('/admin/level-config');
            }
            return redirect()->intended('/join');
        }

        return redirect()->back()->withErrors(['username' => 'Invalid security combinations detected.'])->withInput();
    }
}
