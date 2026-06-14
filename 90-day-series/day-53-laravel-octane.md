# Day 53 - Laravel Octane with FrankenPHP

> **Series:** FreelanceFlow - Laravel Zero to Hero - **Phase 3 - Advanced**  
> **Read time:** 15 min - **Level:** Intermediate

---

> Every normal PHP request starts by booting Laravel from scratch: load config, register service providers, build the container, run middleware, then finally execute your code. Laravel Octane changes that model. It boots the application once, keeps workers hot in memory, and serves many requests from those warm workers. Today we make FreelanceFlow Octane-ready with FrankenPHP, remove long-running-worker state risks, and document the production path.

---

## What We Changed Today

### New files

- `config/octane.php` - published Octane configuration

### Modified files

- `composer.json` - added `laravel/octane` and a `dev:octane` script
- `composer.lock` - locked Octane and its PSR-7 dependency
- `app/Providers/AppServiceProvider.php` - changed request-aware services from singleton to scoped bindings
- `app/Http/Middleware/SetUserLocale.php` - resets application and Carbon locale after every request
- `.env.example` - added Octane environment variables

---

## Step 1 - Install Octane

```bash
composer require laravel/octane
php artisan vendor:publish --provider="Laravel\Octane\OctaneServiceProvider" --tag=octane-config --force
```

On this Windows/Laragon machine, Composer may need Horizon's Unix-only extensions ignored when updating dependencies:

```bash
composer require laravel/octane --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

That is a local Windows workaround only. In Linux production, install the required PHP extensions and run Composer without ignoring platform requirements.

FreelanceFlow also includes a small Windows compatibility shim for Octane's Artisan commands. Native Windows PHP does not define Unix signal support (`SIGINT`, `SIGTERM`, `SIGHUP`) unless `pcntl` is available, so the local command subclasses disable Symfony signal subscription only when `pcntl_signal()` is missing. Linux production keeps Octane's normal signal handling.

---

## Step 2 - Configure Octane

`config/octane.php` now defaults to FrankenPHP:

```php
'server' => env('OCTANE_SERVER', 'frankenphp'),
```

FreelanceFlow also flushes request-aware services on every Octane operation:

```php
'flush' => [
    App\Services\ClientService::class,
    App\Services\DashboardService::class,
    App\Services\InvoiceService::class,
    App\Services\Logger::class,
    App\Services\ProjectService::class,
],
```

The published Octane listener stack is intentionally left close to Laravel's default. Those listeners prepare the app for the next request, flush temporary container instances, handle uploaded files, report worker errors, and close log handlers when a worker stops.

We also watch GraphQL schema files in development:

```php
'watch' => [
    'app',
    'bootstrap',
    'config/**/*.php',
    'database/**/*.php',
    'public/**/*.php',
    'resources/**/*.php',
    'routes',
    'graphql/**/*.graphql',
    'composer.lock',
    '.env',
],
```

---

## Step 3 - Fix Service Lifetimes

The most important Octane rule is simple: anything resolved once and kept in memory can be reused by another request handled by the same worker.

Before today, FreelanceFlow registered the main services as singletons:

```php
$this->app->singleton(ClientService::class);
$this->app->singleton(ProjectService::class);
$this->app->singleton(InvoiceService::class);
$this->app->singleton(DashboardService::class);
$this->app->singleton(Logger::class);
```

Those classes currently read `auth()`, `request()`, or workspace context per method call, so they did not store user data in properties. Still, singleton lifetimes are the wrong default for request-aware services in a long-running worker. If one of those services later adds mutable per-request state, Octane could preserve it across requests.

Use scoped bindings instead:

```php
$this->app->scoped(ClientService::class);
$this->app->scoped(ProjectService::class);
$this->app->scoped(InvoiceService::class);
$this->app->scoped(DashboardService::class);
$this->app->scoped(Logger::class);
```

`scoped()` gives each request a clean resolved instance while still allowing reuse inside that one request. This is the right balance for scalable Laravel apps running under Octane.

---

## Step 4 - Reset Locale State

`Carbon::setLocale()` changes global process state. In PHP-FPM this is usually harmless because the process request lifecycle is short. In Octane, the worker stays alive.

`app/Http/Middleware/SetUserLocale.php` now resets locale state in a `finally` block:

```php
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
```

The `finally` matters. Locale state is reset even if a controller, Livewire action, GraphQL resolver, or downstream middleware throws an exception.

---

## Step 5 - Environment Variables

Add these values to `.env.example`:

```env
# Laravel Octane
OCTANE_SERVER=frankenphp
OCTANE_HTTPS=false
OCTANE_WORKERS=4
OCTANE_MAX_REQUESTS=500
OCTANE_GARBAGE=50
OCTANE_MAX_EXECUTION_TIME=30
```

Start with conservative worker settings, then tune from measurements:

- `OCTANE_WORKERS=4` is a sensible local/default starting point.
- `OCTANE_MAX_REQUESTS=500` restarts workers periodically to cap memory growth.
- `OCTANE_GARBAGE=50` triggers garbage collection after memory crosses the configured threshold.
- `OCTANE_MAX_EXECUTION_TIME=30` prevents a single request from occupying a worker forever.

---

## Step 6 - Development Commands

Normal development still works:

```bash
composer dev
```

Octane development is available with:

```bash
composer dev:octane
```

That starts:

- FrankenPHP Octane on `127.0.0.1:8000`
- the queue listener
- Vite

You can also run Octane manually:

```bash
php artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000 --workers=4 --max-requests=500 --watch
```

Useful production commands:

```bash
php artisan octane:status
php artisan octane:reload
php artisan octane:stop
```

---

## Step 7 - Octane-Safe Code Checklist

Avoid these patterns in long-running workers:

```php
// Unsafe: static request cache can leak data between users.
private static array $clients = [];

// Unsafe: constructor captures the first request's user.
public function __construct(private User $user) {}

// Unsafe: closure static value persists for the worker lifetime.
Event::listen('*', function () {
    static $processed = [];
});
```

Prefer these patterns:

```php
// Safe: read request state at the point of use.
$workspaceId = auth()->user()->currentWorkspace()?->id;

// Safe: external cache is shared intentionally and keyed by tenant/workspace.
Cache::tags(['dashboard', "workspace:{$workspaceId}"])->remember(...);

// Safe: request-aware services use scoped bindings.
$this->app->scoped(DashboardService::class);
```

For FreelanceFlow specifically:

- Services that read auth, request, session, or workspace context should be `scoped()`.
- Redis/database cache keys must include workspace context for tenant data.
- Global state such as locale must be reset after the request.
- Production cache/session/queue stores should use Redis for multi-worker consistency.
- Workers should be reloaded after deployments with `php artisan octane:reload`.

---

## Step 8 - Production Deployment

Run Octane behind a reverse proxy such as Nginx:

```nginx
server {
    listen 80;
    server_name freelanceflow.example.com;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
    }

    location ~* ^/(build|images|vendor|fonts)/.*$ {
        root /var/www/freelanceflow/public;
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

Keep Octane alive with Supervisor:

```ini
[program:freelanceflow-octane]
process_name=%(program_name)s
command=php /var/www/freelanceflow/artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000 --workers=4 --max-requests=500
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/freelanceflow/storage/logs/octane.log
stopwaitsecs=30
```

After deploys:

```bash
php artisan optimize
php artisan migrate --force
php artisan octane:reload
```

---

## Step 9 - Verify

Run formatting and tests:

```bash
vendor/bin/pint
php artisan test
npm run build
```

Then smoke test Octane locally:

```bash
php artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000 --workers=2 --max-requests=100 --watch
```

Visit `http://127.0.0.1:8000`, sign in, open dashboard, clients, projects, invoices, and GraphQL routes. Watch `storage/logs/laravel.log` for worker errors.

---

## What We Learned Today

- Octane keeps Laravel booted in memory, which removes repeated bootstrap overhead.
- Long-running workers make service lifetimes important.
- `scoped()` is the right default for request-aware services.
- Static properties, closure static variables, and constructor-captured request data are dangerous under Octane.
- Global state such as Carbon locale must be reset after every request.
- Worker restart limits are a safety valve, not a substitute for state-safe code.
- Production Octane deployments need process supervision, reverse proxying, Redis-backed shared state, and reloads after code changes.

---

## Day 54 - Phase 3 Review & GitHub Push

Tomorrow we close Phase 3 with a full audit: environment variables, migrations, tests, queues, cache, GraphQL, browser coverage, and deployment readiness.
