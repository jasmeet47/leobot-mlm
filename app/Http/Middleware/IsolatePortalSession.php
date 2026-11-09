<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IsolatePortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseCookie = (string) config('session.cookie');
        $adminPortal = $request->is('admin', 'admin/*')
            || ($request->is('login') && $request->isMethod('POST') && $request->input('portal') === 'admin');
        $cookie = $adminPortal ? $baseCookie.'_admin' : $baseCookie;
        config(['session.cookie' => $cookie]);
        app('session')->driver()->setName($cookie);

        try {
            return $next($request);
        } finally {
            config(['session.cookie' => $baseCookie]);
            app('session')->driver()->setName($baseCookie);
        }
    }
}
