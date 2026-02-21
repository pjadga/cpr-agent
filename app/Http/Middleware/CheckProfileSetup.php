<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckProfileSetup
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
//        if (Auth::check() && !Auth::user()->api_key && !Auth::user()->foundry_url && !$request->routeIs('settings*') && !$request->routeIs('profile-setup*')) {
//            return redirect()->route('settings');
//        }

        return $next($request);
    }
}
