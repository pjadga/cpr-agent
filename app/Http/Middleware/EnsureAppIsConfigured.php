<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppIsConfigured
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $config = $user?->config;

        $hasCharacter = ! empty($user?->character_data);

        if ((! $config || ! $config->api_key || ! $config->foundry_url) && ! $hasCharacter && ! $request->routeIs('settings*')) {
            return redirect()->route('settings');
        }

        return $next($request);
    }
}
