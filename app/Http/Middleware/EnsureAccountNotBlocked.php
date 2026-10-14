<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Fail closed for blocked accounts and for web sessions revoked by a block. */
class EnsureAccountNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->user();
        if ($member === null) {
            abort(401, 'Authentication required.');
        }
        if ($member->is_blocked) {
            abort(403, 'Account is blocked.');
        }

        // API bearer tokens are revoked separately, without affecting
        // multiple-device logins for other, unblocked members.
        if ($request->hasSession() && Auth::guard('web')->check()) {
            $actual = (int) $member->member_login_version;
            $sessionVersion = (int) $request->session()->get('member_login_version', 0);
            if ($actual !== $sessionVersion) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                abort(403, 'Session was revoked. Please sign in again.');
            }
        }
        return $next($request);
    }
}
