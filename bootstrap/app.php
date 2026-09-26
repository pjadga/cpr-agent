<?php

use App\Http\Middleware\CheckProfileSetup;
use App\Http\Middleware\DeduplicateFoundryRequests;
use App\Http\Middleware\EnsureAppIsConfigured;
use App\Http\Middleware\FixTailscaleContentType;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        $middleware->trustProxies(at: '*');
        $middleware->append(AddCspHeaders::class);
        $middleware->api(append: [
            FixTailscaleContentType::class,
        ]);
        $middleware->alias([
            'profile.setup' => CheckProfileSetup::class,
            'app.configured' => EnsureAppIsConfigured::class,
            'foundry.deduplicate' => DeduplicateFoundryRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Reverb being down is a setup problem, not a bug, but it surfaces as a
        // BroadcastException thrown from inside whichever controller happened to
        // dispatch an event. That reaches the browser as a bare 500, and the
        // sheet shows "Request failed with status code 500" -- which names the
        // symptom and none of the cause. Give it a code the client can explain.
        //
        // 503 rather than 500: the request was fine, a dependency is missing.
        $exceptions->render(function (BroadcastException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => 'realtime_unavailable',
                'message' => 'The realtime server (Reverb) is not reachable.',
            ], 503);
        });
    })->create();
