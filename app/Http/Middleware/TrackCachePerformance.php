<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackCachePerformance
{
    public function handle(Request $request, Closure $next)
    {
        $hits   = 0;
        $misses = 0;

        // Listen to cache events
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Cache\Events\CacheHit::class,
            function () use (&$hits) { $hits++; }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Cache\Events\CacheMissed::class,
            function () use (&$misses) { $misses++; }
        );

        $response = $next($request);

        if ($hits + $misses > 0) {
            $hitRate = round(($hits / ($hits + $misses)) * 100, 1);
            Log::debug("Cache performance: {$hits} hits, {$misses} misses, {$hitRate}% hit rate", [
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }
}