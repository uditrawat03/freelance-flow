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
        $defaultLocale = config('app.locale', 'en');
        $locale = $request->user()?->getLocale() ?? $defaultLocale;

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        try {
            return $next($request);
        } finally {
            app()->setLocale($defaultLocale);
            Carbon::setLocale($defaultLocale);
        }
    }
}
