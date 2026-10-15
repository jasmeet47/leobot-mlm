<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Unauthenticated users cannot access admin routes.
        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // Blocked accounts must never retain administrator access,
        // even when their role and activation status remain unchanged.
        if ($user->is_blocked) {
            abort(403, 'Unauthorized.');
        }

        // Only active accounts can access admin routes.
        if ($user->status !== 'active') {
            abort(403, 'Unauthorized.');
        }

        // Authorization is based on role, not username.
        if (!in_array(
            $user->role,
            ['admin', 'super_admin'],
            true
        )) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
