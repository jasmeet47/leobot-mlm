<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LoginCaptcha
{
    public static function issue(Request $request): array
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $request->session()->put('lumiflowon.login_captcha', ['answer' => (string) ($left + $right), 'expires_at' => time() + 300]);

        return compact('left', 'right');
    }

    public static function verify(Request $request): void
    {
        if (config('leobot_auth.captcha_enabled', true) !== true) {
            return;
        }
        $expected = $request->session()->pull('lumiflowon.login_captcha');
        $answer = $request->input('login_captcha');
        if (! is_array($expected) || ($expected['expires_at'] ?? 0) < time() || ! is_string($answer)
            || ! preg_match('/^\d{1,2}$/D', $answer) || ! hash_equals($expected['answer'], $answer)) {
            throw ValidationException::withMessages(['login_captcha' => 'Please solve the login security check. Refresh the page if it expired.']);
        }
    }
}
