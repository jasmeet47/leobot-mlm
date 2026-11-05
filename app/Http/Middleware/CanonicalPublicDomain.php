<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keep the public apex out of the isolated Beta account and financial database. */
final class CanonicalPublicDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.release_stage') !== 'beta' || strtolower($request->getHost()) !== 'lumiflowon.com') {
            return $next($request);
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            $message = 'This form has moved. Open https://www.lumiflowon.com/join before submitting. No action was performed.';

            return $request->expectsJson() ? response()->json(['message' => $message], 409) : response($message, 409);
        }
        // Never forward Beta sessions, referral identifiers, OTPs or reset tokens to Production.
        $path = in_array($request->path(), ['/', ''], true) ? '/' : '/join';

        return redirect()->away('https://www.lumiflowon.com'.$path, 302);
    }
}
