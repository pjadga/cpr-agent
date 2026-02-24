<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Csp\AddCspHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias([
            'profile.setup' => \App\Http\Middleware\CheckProfileSetup::class,
            'app.configured' => \App\Http\Middleware\EnsureAppIsConfigured::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(AddCspHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
