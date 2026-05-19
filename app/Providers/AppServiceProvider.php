<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ClientService;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Services\ProjectService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Invoice;
use App\Observers\InvoiceObserver;
use Illuminate\Support\Facades\Gate;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Eloquent\EloquentClientRepository;
use App\Repositories\Eloquent\EloquentInvoiceRepository;
use App\Repositories\Eloquent\EloquentProjectRepository;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repository bindings — interface to Eloquent implementation
        $this->app->bind(ClientRepositoryInterface::class,  EloquentClientRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);

        // Services — singletons as before
        $this->app->singleton(ClientService::class);
        $this->app->singleton(ProjectService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(DashboardService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Throws an exception if a relationship is lazy-loaded (not eager-loaded)
        // Only enable in local/testing environments
        Model::preventLazyLoading(! app()->isProduction());

        // Only allow users who have at least one paid invoice to access the analytics page
        Gate::define('access-analytics', function (User $user) {
            return $user->invoices()->paid()->exists();
        });

        // Only admin users can manage other users (for when we add teams)
        Gate::define('manage-users', function (User $user) {
            return $user->role === 'admin';
        });

        // Before hook — runs before all other authorization checks
        // Useful for super-admin bypass
        Gate::before(function (User $user, string $ability) {
            if ($user->is_super_admin) {
                return true; // bypass all checks
            }
        });

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

        Invoice::observe(InvoiceObserver::class);
    }
}
