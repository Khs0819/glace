<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app runs behind a TLS-terminating reverse proxy: the browser speaks
        // HTTPS to the proxy, the proxy speaks plain HTTP to nginx/php-fpm. Without
        // trusting it, Laravel believes the request is insecure and generates
        // http:// asset URLs, which the browser then blocks as mixed content —
        // Filament's select/textarea/file-upload modules never load and the
        // dashboard controls stop responding.
        $middleware->trustProxies(at: '*');

        // Storefront accounts. `customer` demands a token; `customer.optional`
        // binds one when present and lets a guest through when it is not.
        // The app has no generic `login` route — Filament owns the only one.
        // Without this, the `auth` middleware on the receipt routes throws
        // RouteNotFoundException instead of sending a guest to sign in.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        $middleware->alias([
            'customer'          => \App\Http\Middleware\AuthenticateCustomer::class,
            'customer.optional' => \App\Http\Middleware\AuthenticateCustomerOptional::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
