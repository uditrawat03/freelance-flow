<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Tell Laravel this request expects JSON
        // This ensures validation errors, auth errors, and 404s
        // all return JSON instead of HTML redirects
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}