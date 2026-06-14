<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->getLocale() ?? config('app.locale', 'en');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
