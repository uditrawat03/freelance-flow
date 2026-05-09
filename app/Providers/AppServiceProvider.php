<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Throws an exception if a relationship is lazy-loaded (not eager-loaded)
        // Only enable in local/testing environments
        Model::preventLazyLoading(! app()->isProduction());
    }
}
