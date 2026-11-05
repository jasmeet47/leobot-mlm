<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminSaveFeedback
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->is('admin/*') && $request->user() && in_array($request->user()->role, ['admin', 'super_admin'], true) && $response->getStatusCode() === 200 && str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $markup = view('partials.admin-feedback')->render();
            $response->setContent(str_replace('</body>', $markup.'</body>', $response->getContent()));
        }

        return $response;
    }
}
