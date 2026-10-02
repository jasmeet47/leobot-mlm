<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    // 1. लाइव स्पॉन्सर नाम चेक करने का लॉजिक (API)
    public function verifySponsor(Request $request)
    {
        $request->validate([
            'sponsor_id' => 'required|string'
        ]);
        
        $sponsor = User::where('username', $request->sponsor_id)->first();

        if ($sponsor) {
            return response()->json([
                'success' => true, 
                'sponsor_name' => $sponsor->name
            ], 200);
        }

        return response()->json([
            'success' => false, 
            'message' => 'Invalid Sponsor ID'
        ], 404);
    }

    // 2. नया यूजर रजिस्टर करने का मुख्य लॉजिक (API)
    public function register(Request $request)
    {
        $request->validate([
            'sponsor_id'   => 'required|string|exists:users,username',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'phone'        => 'required|string|max:20',
            'password'     => 'required|string|min:8|confirmed',
            'security_pin' => 'required|numeric|digits:6',
        ]);

        // ऑटोमैटिक यूनीक यूजरनेम जनरेट करना (जैसे: TX100234)
        do {
            $username = 'TX' . rand(100000, 999999);
        } while (User::where('username', $username)->exists());

        $user = User::create([
            'username'     => $username,
            'sponsor_id'   => $request->sponsor_id,
            'name'         => $request->name,
            'email'        => $request->email,
            'phone'        => $request->phone,
            'password'     => Hash::make($request->password), // पासवर्ड एन्क्रिप्ट करें
            'security_pin' => Hash::make($request->security_pin), // सुरक्षा पिन एन्क्रिप्ट करें
            'status'       => 'inactive',
        ]);
// नए यूजर के जुड़ते ही ऊपर के 41 अपलाइनों की टीम काउंट अपडेट करने का ट्रिगर
TreeService::updateUplineTree($user->id, 'register');
        return response()->json([
            'success' => true,
            'message' => 'Registration successful!',
            'data' => [
                'username' => $user->username, 
                'name' => $user->name
            ]
        ], 201);
    }
}
