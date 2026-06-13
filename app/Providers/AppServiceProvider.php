<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ClientService;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Services\ProjectService;
use App\Support\ApiRateLimiters;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Tag;
use App\Observers\TagObserver;

use App\Observers\ClientObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ProjectObserver;

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
        // Repository bindings: interface to Eloquent implementation.
        $this->app->bind(ClientRepositoryInterface::class,  EloquentClientRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);

        // Services: singletons as before.
        $this->app->singleton(ClientService::class);
        $this->app->singleton(ProjectService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(\App\Services\Logger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(\Laravel\Dusk\Dusk::class)) {
            \Laravel\Dusk\Dusk::register(['environments' => ['local', 'testing', 'dusk']]);
        }

        if (app()->isProduction()) {
            $this->validateRequiredConfig();
        }
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

        // Before hook: runs before all other authorization checks.
        // Useful for super-admin bypass
        Gate::before(function (User $user, string $ability) {
            if ($user->is_super_admin) {
                return true; // bypass all checks
            }
        });

        ApiRateLimiters::register();

        Client::observe(ClientObserver::class);
        Project::observe(ProjectObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Tag::observe(TagObserver::class);

    }

    private function validateRequiredConfig(): void
    {
        $required = [
            'app.key'              => 'APP_KEY',
            'database.connections.mysql.host' => 'DB_HOST',
            'cashier.secret'       => 'STRIPE_SECRET',
            'cashier.webhook.secret' => 'STRIPE_WEBHOOK_SECRET',
            'mail.from.address'    => 'MAIL_FROM_ADDRESS',
        ];

        $missing = [];

        foreach ($required as $configKey => $envKey) {
            if (empty(config($configKey))) {
                $missing[] = $envKey;
            }
        }

        if (! empty($missing)) {
            throw new \RuntimeException(
                'Missing required environment variables: ' . implode(', ', $missing)
            );
        }
    }
}
