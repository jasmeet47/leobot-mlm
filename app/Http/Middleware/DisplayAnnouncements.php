<?php

namespace App\Http\Middleware;

use App\Services\AnnouncementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DisplayAnnouncements
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && str_contains($response->headers->get('Content-Type', ''), 'text/html') && AnnouncementService::page($request)) {
            $items = AnnouncementService::forPage($request);
            if ($items) {
                $markup = view('partials.announcements', ['items' => $items])->render();
                $response->setContent(str_replace('</body>', $markup.'</body>', $response->getContent()));
                $response->headers->set('Cache-Control', 'private, no-store');
            }
        }

        return $response;
    }
}
