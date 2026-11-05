<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\AdminSaveFeedback;
use App\Http\Middleware\CanonicalPublicDomain;
use App\Http\Middleware\DisplayAnnouncements;
use App\Http\Middleware\EnsureAccountNotBlocked;
use App\Http\Middleware\MeasureFullLoginLifecycle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Redirect Guests to Login Page
        |--------------------------------------------------------------------------
        |
        | LeoBot uses /join for member login and registration.
        | Guests accessing protected web pages are sent there.
        |
        */

        $middleware->prepend(MeasureFullLoginLifecycle::class);
        $middleware->prepend(CanonicalPublicDomain::class);
        $middleware->redirectGuestsTo('/join');
        $middleware->appendToGroup('web', [AdminSaveFeedback::class, DisplayAnnouncements::class]);

        /*
        |--------------------------------------------------------------------------
        | Custom Middleware Aliases
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            'admin' => AdminOnly::class,
            'account.not_blocked' => EnsureAccountNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $error, Request $request) {
            if ($request->is('admin/*') && ! $request->isMethod('GET') && ! $request->expectsJson()) {
                return response()->view('admin.save-error', ['failureMessage' => 'Your changes could not be saved. Please try again.'], 500);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $error, Request $request) {
            if ($request->is('admin/*') && ! $request->isMethod('GET') && ! $request->expectsJson() && $error->getStatusCode() === 409) {
                return response()->view('admin.save-error', ['failureMessage' => 'This record changed or cannot be updated. Refresh the page and try again.'], 409);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
