<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            !$request->user() ||
            strtoupper((string) $request->user()->username) !== 'ADMIN' ||
            $request->user()->status !== 'active'
        ) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
