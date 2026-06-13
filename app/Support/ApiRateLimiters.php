<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ApiRateLimiters
{
    private const DEFAULT_LIMITS = [
        'api' => [
            'authenticated_per_minute' => 60,
            'guest_per_minute' => 30,
        ],
        'api_reads' => [
            'authenticated_per_minute' => 120,
            'guest_per_minute' => 30,
        ],
        'token_creation' => [
            'per_minute' => 5,
        ],
    ];

    public static function register(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return self::perMinute($request, 'api');
        });

        RateLimiter::for('token-creation', function (Request $request) {
            return Limit::perMinute(self::limit('token_creation', 'per_minute'))
                ->by($request->ip());
        });

        RateLimiter::for('api-reads', function (Request $request) {
            return self::perMinute($request, 'api_reads');
        });
    }

    private static function perMinute(Request $request, string $key): Limit
    {
        if ($request->user()) {
            return Limit::perMinute(self::limit($key, 'authenticated_per_minute'))
                ->by('user:'.$request->user()->getAuthIdentifier());
        }

        return Limit::perMinute(self::limit($key, 'guest_per_minute'))
            ->by('ip:'.$request->ip());
    }

    private static function limit(string $key, string $field): int
    {
        return (int) config(
            "freelanceflow.rate_limits.{$key}.{$field}",
            self::DEFAULT_LIMITS[$key][$field]
        );
    }
}
