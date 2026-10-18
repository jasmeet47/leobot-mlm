<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureLoginRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        try {
            return $next($request);
        } finally {
            Log::info('leobot.login.request_timing', [
                'elapsed_ms' => round(
                    (hrtime(true) - $start) / 1000000,
                    2
                ),
            ]);
        }
    }
}