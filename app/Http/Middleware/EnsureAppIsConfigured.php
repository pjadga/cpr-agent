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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $config = $request->user()?->config;

        if ((!$config || !$config->api_key || !$config->base_url) && !$request->routeIs('config.*') && !$request->routeIs('settings*')) {
            return redirect()->route('config.index');
        }

        return $next($request);
    }
}
