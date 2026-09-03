<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowEmbedding
{
    /**
     * Allow this route to be embedded in a cross-origin <iframe> (Foundry).
     *
     * The app's CSP (config/csp.php -> App\Support\Csp\Policy) sets no
     * `frame-ancestors` directive and the app sets no `X-Frame-Options`, so
     * framing is permitted by default. We strip `X-Frame-Options` defensively
     * in case a reverse proxy injects it. If a future deployment enables a
     * restrictive `frame-ancestors`, that becomes a Phase 4 hardening item.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->remove('X-Frame-Options');

        return $response;
    }
}
