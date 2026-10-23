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
        // Validate login credentials.
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Allow login by username or email.
        $user = User::query()
            ->where('username', $validated['login'])
            ->orWhere('email', $validated['login'])
            ->first();

        // Do not reveal whether the account exists.
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username/email or password.',
            ], 401);
        }

        // Only known member statuses can receive a token.
        if ($user->deleted_at !== null || $user->is_blocked || !in_array($user->status, ['active', 'inactive'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not available for login.',
            ], 403);
        }

        // Restricted token. No financial permissions.
        $token = $user->createToken(
            'member_auth',
            ['member:read']
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ],
        ], 200);
    }
}
