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
            // An XHR cannot follow a redirect to a login-shaped HTML page in any
            // useful way: axios sees a 200 full of markup, or a CORS-ish failure,
            // and reports something that has nothing to do with the real cause.
            // Answer in the same language the caller asked in.
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'foundry_not_configured',
                    'message' => 'No Foundry connection has been set up for this account yet.',
                ], 409);
            }

            return redirect()->route('settings');
        }

        return $next($request);
    }
}
