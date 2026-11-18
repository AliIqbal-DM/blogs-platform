<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Tenant and central route names differ, so pick the login and home routes for the current domain.
        $middleware->redirectGuestsTo(fn () => route(tenancy()->initialized ? 'tenant.login' : 'login'));
        $middleware->redirectUsersTo(fn () => route(tenancy()->initialized ? 'tenant.dashboard' : 'dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
