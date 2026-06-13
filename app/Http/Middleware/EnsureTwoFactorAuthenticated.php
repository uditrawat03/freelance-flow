<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasTwoFactorEnabled() || $request->session()->get('two_factor_verified') === true) {
            return $next($request);
        }

        if ($request->routeIs('two-factor.challenge', 'logout')) {
            return $next($request);
        }

        $request->session()->put('two_factor_intended', $request->fullUrl());

        return redirect()->route('two-factor.challenge');
    }
}
