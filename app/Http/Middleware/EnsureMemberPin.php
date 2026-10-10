<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberPin
{
    public static function hasConfiguredPin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $stored = (string) $user->security_pin;

        // A legacy plain-text PIN is NOT accepted as a completed setup.
        return $stored !== '' && password_get_info($stored)['algoName'] !== 'unknown';
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Authentication required.');
        }

        // The member dashboard is not an alternative admin interface.
        if (strtoupper((string) $user->username) === 'ADMIN') {
            abort(403, 'Member dashboard is not available to administrators.');
        }

        if (!self::hasConfiguredPin($user)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'security_pin_required',
                    'message' => 'Please set your Security PIN before opening the dashboard.',
                ], 403);
            }

            return redirect()->route('security-pin.form');
        }

        return $next($request);
    }
}
