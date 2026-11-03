<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\RegistrationEmailOtpService;
use Illuminate\Http\Request;

final class RegistrationEmailOtpController extends Controller
{
    public function send(Request $request, RegistrationEmailOtpService $service)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $token = $service->send($data['email'], $request->ip() ?? 'unknown');
        return response()->json(['success' => true, 'message' => 'OTP sent. Check your email.', 'challenge_token' => $token]);
    }

    public function verify(Request $request, RegistrationEmailOtpService $service)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'challenge_token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $service->verify($data['email'], $data['otp'], $data['challenge_token']);
        return response()->json(['success' => true, 'message' => 'Email OTP verified.']);
    }
}
