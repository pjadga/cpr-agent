<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FixTailscaleContentType
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        // If it is a POST to the API and the header was stripped by the proxy
        if ($request->isMethod('POST') && ! $request->hasHeader('Content-Type')) {
            $request->headers->set('Content-Type', 'application/json');
        }

        return $next($request);
    }
}
