<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        // 1. इनपुट डेटा को वैलिडेट करें (यूज़र आईडी या ईमेल दोनों से लॉगिन कर सकते हैं)
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        // 2. डेटाबेस में यूज़र को खोजें (Username या Email के आधार पर)
        $user = User::where('username', $request->login)
                    ->orWhere('email', $request->login)
                    ->first();

        // 3. चेक करें कि यूज़र मौजूद है और पासवर्ड सही है या नहीं
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username/email or password.'
            ], 401);
        }

        // 4. पुराने सभी टोकन्स को डिलीट करें (Single Device Login सुरक्षा नियम)
        $user->tokens()->delete();

        // 5. नया सिक्योर एक्सेस टोकन जेनरेट करें
        $token = $user->createToken('auth_token')->plainTextToken;

        // 6. सफलता का रिस्पॉन्स भेजें
        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'username' => $user->username,
                'name'     => $user->name,
                'email'    => $user->email,
                'status'   => $user->status
            ]
        ], 200);
    }
}
