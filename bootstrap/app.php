<?php

use App\Http\Middleware\ApplyPreferences;
use App\Http\Middleware\RedirectIfSignedIn;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireSignedIn;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ApplyPreferences::class]);
        // These two endpoints are only called by the app's own web view with fetch(); the
        // NativePHP web view does not always forward custom headers, which caused
        // "CSRF token mismatch". They stay protected by the signed-in check.
        $middleware->validateCsrfTokens(except: [
            'events/*/card',
            'client-log',
        ]);
        $middleware->alias([
            'signed.in' => RequireSignedIn::class,
            'admin' => RequireAdmin::class,
            'guest.only' => RedirectIfSignedIn::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
