<?php

namespace App\Providers;

use App\Services\InvoiceService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(InvoiceService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Throws an exception if a relationship is lazy-loaded (not eager-loaded)
        // Only enable in local/testing environments
        Model::preventLazyLoading(! app()->isProduction());

        // Standard API rate limit: 60 requests per minute per token/IP
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Strict limit for token creation: 5 per minute per IP
        // Prevents brute-force credential attacks
        RateLimiter::for('token-creation', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Relaxed limit for read-heavy endpoints: 120 per minute
        RateLimiter::for('api-reads', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });
    }
}
