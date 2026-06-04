# Day 38 — Redis Caching

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 3 — Advanced**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"The database cache driver works — we have been using it since Day 28. But storing cache entries in MySQL means every cache read and write hits the same database that handles application queries. Redis is a dedicated in-memory data store built specifically for caching. It is orders of magnitude faster, supports cache tags for group invalidation, and keeps cache and database concerns cleanly separated. Today we migrate FreelanceFlow's caching to Redis."*

---

## What We Are Building Today

1. **Install and configure Redis** — connection, driver switch
2. **Migrate all `Cache::remember()` calls** to Redis
3. **Cache tags** — group-invalidate related cache entries together
4. **Update `DashboardService`** with Redis-backed cache tags
5. **Update `ClientService`** and `InvoiceService`
6. **Benchmark** — measure the difference
7. **Redis CLI** — inspect and manage cache entries

---

## Step 1 — Install Redis

For macOS:

```bash
brew install redis
brew services start redis
```

For Ubuntu/Debian:

```bash
sudo apt-get install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

Verify Redis is running:

```bash
redis-cli ping
# PONG
```

Install or enable the PHP Redis client. For production, prefer the `phpredis` extension because it is faster and supports persistent connections:

```bash
# Ubuntu/Debian example
sudo apt-get install php-redis
php -m | grep redis
```

If your environment cannot install PHP extensions, use Predis as a fallback:

```bash
composer require predis/predis
```

---

## Step 2 — Configure Laravel for Redis

Update `.env`:

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_PERSISTENT=false
REDIS_MAX_RETRIES=3
REDIS_BACKOFF_ALGORITHM=decorrelated_jitter
REDIS_BACKOFF_BASE=100
REDIS_BACKOFF_CAP=1000

# Switch cache, session, and queue to Redis
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

Open `config/database.php` and verify the Redis configuration:

```php
'redis' => [

    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster'    => env('REDIS_CLUSTER', 'redis'),
        'prefix'     => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
        'persistent' => env('REDIS_PERSISTENT', false),
    ],

    'default' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', '6379'),
        'database'          => env('REDIS_DB', '0'),
        'max_retries'       => env('REDIS_MAX_RETRIES', 3),
        'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
        'backoff_base'      => env('REDIS_BACKOFF_BASE', 100),
        'backoff_cap'       => env('REDIS_BACKOFF_CAP', 1000),
    ],

    'cache' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', '6379'),
        'database'          => env('REDIS_CACHE_DB', '1'), // separate DB for cache
        'max_retries'       => env('REDIS_MAX_RETRIES', 3),
        'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
        'backoff_base'      => env('REDIS_BACKOFF_BASE', 100),
        'backoff_cap'       => env('REDIS_BACKOFF_CAP', 1000),
    ],

],
```

Using a separate Redis database (`REDIS_CACHE_DB=1`) for cache keeps cache and session data isolated. You can flush the cache without touching sessions. In larger deployments, use separate Redis instances or managed Redis databases for cache, queues, and sessions so one workload cannot exhaust memory for the others.

Open `config/cache.php` and verify the Redis store:

```php
'stores' => [

    'redis' => [
        'driver'            => 'redis',
        'connection'        => 'cache',
        'lock_connection'   => 'default',
    ],

],
```

---

## Step 3 — Cache Tags

Cache tags group related cache entries together. You can invalidate an entire group with one call instead of manually tracking and deleting individual keys.

Without tags — clearing workspace cache requires knowing every key:

```php
// ❌ Fragile — must remember every key format
Cache::forget("dashboard_stats_{$workspaceId}");
Cache::forget("revenue_chart_3_{$workspaceId}");
Cache::forget("revenue_chart_6_{$workspaceId}");
Cache::forget("revenue_chart_12_{$workspaceId}");
Cache::forget("client_stats_{$workspaceId}");
```

With tags — one call clears everything tagged for the workspace:

```php
// ✓ Clean — clear everything in the workspace tag group
Cache::tags(["workspace:{$workspaceId}"])->flush();
```

> **Important:** Cache tags require a driver that supports them. The `file` and `database` drivers do NOT support tags. Redis and Memcached do. This is another reason to migrate to Redis.

---

## Production Fix — Centralize Cache Tag Safety

The current code uses `Cache::tags()` in services and observers. That is fine when `CACHE_STORE=redis`, but it will throw an exception if local development still uses `database` or `file`. For a scalable app, hide that driver detail behind one small service.

Create `app/Services/CacheService.php`:

```php
<?php

namespace App\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    public function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    public function workspaceKey(string $name, int|string|null $workspaceId, mixed ...$parts): string
    {
        return collect([$name, $workspaceId, ...$parts])
            ->filter(fn ($part) => $part !== null && $part !== '')
            ->implode(':');
    }

    public function remember(array $tags, string $key, int $ttl, Closure $callback): mixed
    {
        if ($this->supportsTags()) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    public function flush(array $tags, array $fallbackKeys = []): void
    {
        if ($this->supportsTags()) {
            Cache::tags($tags)->flush();
            return;
        }

        foreach ($fallbackKeys as $key) {
            Cache::forget($key);
        }
    }

    public function lock(string $name, int $seconds): Lock
    {
        return Cache::lock($name, $seconds);
    }
}
```

Now the application can use Redis tags in production without breaking local environments that still use the database cache driver.

---

## Step 4 — Update DashboardService with Cache Tags

Use `CacheService` in `DashboardService` so the cache stays workspace-scoped, TTL-driven, and safe when tags are unavailable:

```php
use App\Services\CacheService;

class DashboardService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly ProjectRepositoryInterface $projects,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CacheService $cache,
    ) {}

    private function workspaceId(): int|null
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    private function tags(string ...$extra): array
    {
        return [
            'dashboard',
            "workspace:{$this->workspaceId()}",
            ...$extra,
        ];
    }

    public function stats(): array
    {
        $workspaceId = $this->workspaceId();
        $key = $this->cache->workspaceKey('dashboard:stats', $workspaceId);

        return $this->cache->remember(
            $this->tags(),
            $key,
            config('freelanceflow.cache.ttl.dashboard_stats'),
            fn () => [
                'total_clients' => Client::active()->count(),
                'active_projects' => Project::active()->count(),
                'unpaid_invoices' => Invoice::unpaid()->count(),
                'overdue_invoices' => Invoice::overdue()->count(),
                'total_revenue' => $this->invoices->totalRevenue(),
                'revenue_this_month' => Invoice::paid()
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('total'),
            ],
        );
    }

    public function revenueChart(int $months = 12): array
    {
        $workspaceId = $this->workspaceId();
        $key = $this->cache->workspaceKey('dashboard:revenue_chart', $workspaceId, $months);

        return $this->cache->remember(
            $this->tags(),
            $key,
            config('freelanceflow.cache.ttl.revenue_chart'),
            function () use ($months) {
                $chart = $this->invoices->revenueByMonth($months);
                $chart['total'] = array_sum($chart['data']);

                return $chart;
            },
        );
    }

    public function projectStatusBreakdown(): array
    {
        $workspaceId = $this->workspaceId();
        $key = $this->cache->workspaceKey('dashboard:project_status', $workspaceId);

        return $this->cache->remember(
            $this->tags('projects'),
            $key,
            config('freelanceflow.cache.ttl.project_status'),
            fn () => $this->projects->statusBreakdown(),
        );
    }

    public function recentActivity(): array
    {
        $workspaceId = $this->workspaceId();
        $key = $this->cache->workspaceKey('dashboard:recent_activity', $workspaceId);

        return $this->cache->remember(
            $this->tags(),
            $key,
            config('freelanceflow.cache.ttl.recent_activity'),
            fn () => [
                'clients' => Client::latest()->limit(5)->get(),
                'projects' => Project::with('client')->latest()->limit(5)->get(),
                'invoices' => Invoice::with('client')->latest()->limit(5)->get(),
            ],
        );
    }

    public function bustCache(): void
    {
        $workspaceId = $this->workspaceId();

        $this->cache->flush($this->tags(), [
            $this->cache->workspaceKey('dashboard:stats', $workspaceId),
            $this->cache->workspaceKey('dashboard:project_status', $workspaceId),
            $this->cache->workspaceKey('dashboard:recent_activity', $workspaceId),
            $this->cache->workspaceKey('dashboard:revenue_chart', $workspaceId, 3),
            $this->cache->workspaceKey('dashboard:revenue_chart', $workspaceId, 6),
            $this->cache->workspaceKey('dashboard:revenue_chart', $workspaceId, 12),
        ]);
    }
}
```

The important changes are:

- Keys use the workspace ID, not the user ID.
- TTLs come from config instead of hardcoded `300`.
- `projectStatusBreakdown()` and `recentActivity()` are cached too.
- Fallback keys keep local database/file cache usable.

The older raw `Cache::tags()` example below still shows the underlying idea, but the `CacheService` version is the production-safe pattern to use in the app.

```php
<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private readonly ClientRepositoryInterface  $clients,
        private readonly ProjectRepositoryInterface $projects,
        private readonly InvoiceRepositoryInterface $invoices,
    ) {}

    private function workspaceId(): int|null
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    private function cache(): \Illuminate\Cache\TaggedCache
    {
        return Cache::tags([
            'dashboard',
            "workspace:{$this->workspaceId()}",
        ]);
    }

    public function stats(): array
    {
        return $this->cache()->remember(
            "stats_{$this->workspaceId()}",
            config('freelanceflow.dashboard.cache_ttl', 300),
            function () {
                return [
                    'total_clients'      => Client::active()->count(),
                    'active_projects'    => Project::active()->count(),
                    'unpaid_invoices'    => Invoice::unpaid()->count(),
                    'overdue_invoices'   => Invoice::overdue()->count(),
                    'total_revenue'      => $this->invoices->totalRevenue(),
                    'revenue_this_month' => Invoice::paid()
                        ->whereMonth('paid_at', now()->month)
                        ->whereYear('paid_at', now()->year)
                        ->sum('total'),
                ];
            }
        );
    }

    public function revenueChart(int $months = 12): array
    {
        return $this->cache()->remember(
            "revenue_chart_{$months}_{$this->workspaceId()}",
            config('freelanceflow.dashboard.cache_ttl', 300),
            function () use ($months) {
                $chart          = $this->invoices->revenueByMonth($months);
                $chart['total'] = array_sum($chart['data']);
                return $chart;
            }
        );
    }

    public function projectStatusBreakdown(): array
    {
        return $this->cache()->remember(
            "project_status_{$this->workspaceId()}",
            config('freelanceflow.dashboard.cache_ttl', 300),
            fn () => $this->projects->statusBreakdown()
        );
    }

    public function recentActivity(): array
    {
        // Recent activity changes frequently — short TTL, no tags
        return Cache::remember(
            "recent_activity_{$this->workspaceId()}",
            60, // 1 minute
            function () {
                return [
                    'clients'  => Client::latest()->limit(5)->get(),
                    'projects' => Project::with('client')->latest()->limit(5)->get(),
                    'invoices' => Invoice::with('client')->latest()->limit(5)->get(),
                ];
            }
        );
    }

    public function overdueItems(): array
    {
        return [
            'invoices' => $this->invoices->overdueInvoices()->take(5),
            'projects' => $this->projects->overdueProjects()->take(5),
        ];
    }

    /**
     * Bust all dashboard cache entries for the current workspace.
     * Uses tags — one call clears all dashboard cache for this workspace.
     */
    public function bustCache(): void
    {
        Cache::tags([
            'dashboard',
            "workspace:{$this->workspaceId()}",
        ])->flush();

        // Also clear recent activity (not tagged)
        Cache::forget("recent_activity_{$this->workspaceId()}");
    }
}
```

---

## Step 5 — Update ClientService with Cache Tags

```php
<?php

namespace App\Services;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {}

    private function workspaceId(): int|null
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    public function list(
        string $search  = '',
        string $status  = '',
        int    $perPage = 15,
    ): LengthAwarePaginator {
        return $this->clients->paginate($search, $status, $perPage);
    }

    public function create(array $data): \App\Models\Client
    {
        $client = $this->clients->create($data);
        $this->bustCache();
        return $client;
    }

    public function update(\App\Models\Client $client, array $data): \App\Models\Client
    {
        $updated = $this->clients->update($client, $data);
        $this->bustCache();
        return $updated;
    }

    public function delete(\App\Models\Client $client): void
    {
        $this->clients->delete($client);
        $this->bustCache();
    }

    public function statistics(): array
    {
        return Cache::tags([
            'clients',
            "workspace:{$this->workspaceId()}",
        ])->remember(
            "client_stats_{$this->workspaceId()}",
            300,
            fn () => $this->clients->countByStatus()
        );
    }

    public function bustCache(): void
    {
        // Bust client-specific cache
        Cache::tags([
            'clients',
            "workspace:{$this->workspaceId()}",
        ])->flush();

        // Also bust dashboard since client count changes affect it
        Cache::tags([
            'dashboard',
            "workspace:{$this->workspaceId()}",
        ])->flush();
    }
}
```

---

## Step 6 — Update InvoiceService with Cache Tags

```php
// app/Services/InvoiceService.php
// Add cache busting after create, update, delete

public function create(array $data): \App\Models\Invoice
{
    $invoice = $this->invoices->create($data);
    $this->bustCache();
    return $invoice;
}

public function update(\App\Models\Invoice $invoice, array $data): \App\Models\Invoice
{
    $updated = $this->invoices->update($invoice, $data);
    $this->bustCache();
    return $updated;
}

public function delete(\App\Models\Invoice $invoice): void
{
    $this->deletePdf($invoice);
    $this->invoices->delete($invoice);
    $this->bustCache();
}

private function workspaceId(): int|null
{
    return auth()->user()->currentWorkspace()?->id;
}

public function bustCache(): void
{
    Cache::tags([
        'invoices',
        'dashboard',
        "workspace:{$this->workspaceId()}",
    ])->flush();
}
```

---

## Step 7 — Model Observer for Automatic Cache Busting

Instead of manually calling `bustCache()` everywhere, use model observers to automatically bust the relevant cache whenever a model is saved or deleted.

```bash
php artisan make:observer ClientObserver --model=Client
php artisan make:observer ProjectObserver --model=Project
php artisan make:observer InvoiceObserver --model=Invoice
```

`app/Observers/ClientObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Client;
use Illuminate\Support\Facades\Cache;

class ClientObserver
{
    private function workspaceId(Client $client): int|null
    {
        return $client->workspace_id;
    }

    public function created(Client $client): void
    {
        $this->bustCache($client);
    }

    public function updated(Client $client): void
    {
        $this->bustCache($client);
    }

    public function deleted(Client $client): void
    {
        $this->bustCache($client);
    }

    private function bustCache(Client $client): void
    {
        $workspaceId = $this->workspaceId($client);

        Cache::tags([
            'clients',
            'dashboard',
            "workspace:{$workspaceId}",
        ])->flush();
    }
}
```

`app/Observers/InvoiceObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $this->bustCache($invoice);
    }

    public function updated(Invoice $invoice): void
    {
        $this->bustCache($invoice);
    }

    public function deleted(Invoice $invoice): void
    {
        $this->bustCache($invoice);
    }

    private function bustCache(Invoice $invoice): void
    {
        Cache::tags([
            'invoices',
            'dashboard',
            "workspace:{$invoice->workspace_id}",
        ])->flush();
    }
}
```

`app/Observers/ProjectObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class ProjectObserver
{
    public function created(Project $project): void
    {
        $this->bustCache($project);
    }

    public function updated(Project $project): void
    {
        $this->bustCache($project);
    }

    public function deleted(Project $project): void
    {
        $this->bustCache($project);
    }

    private function bustCache(Project $project): void
    {
        Cache::tags([
            'projects',
            'dashboard',
            "workspace:{$project->workspace_id}",
        ])->flush();
    }
}
```

Register all three observers in `AppServiceProvider`:

```php
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Observers\ClientObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ProjectObserver;

public function boot(): void
{
    Client::observe(ClientObserver::class);
    Project::observe(ProjectObserver::class);
    Invoice::observe(InvoiceObserver::class);
}
```

Now every time a client, project, or invoice is created, updated, or deleted — the relevant cache tags are automatically flushed. No service method needs to call `bustCache()` manually anymore.

---

## Step 8 — Inspect Redis with Redis CLI

```bash
# Connect to Redis
redis-cli

# List all keys in the cache DB (database 1)
redis-cli -n 1 KEYS "*"

# Get a specific key
redis-cli -n 1 GET "freelanceflow_database_dashboard_stats_1"

# Get TTL of a key (in seconds, -1 = no expiry, -2 = does not exist)
redis-cli -n 1 TTL "freelanceflow_database_dashboard_stats_1"

# Count all keys in the cache DB
redis-cli -n 1 DBSIZE

# Flush only the cache DB (not sessions or queue)
redis-cli -n 1 FLUSHDB

# Monitor all Redis commands in real time (great for debugging)
redis-cli MONITOR
```

---

## Step 9 — Redis Cache Reference

```php
// Basic operations
Cache::put('key', $value, 300);          // store for 300 seconds
Cache::get('key');                        // retrieve
Cache::get('key', 'default');             // retrieve with default
Cache::forget('key');                     // delete one key
Cache::flush();                           // delete ALL cache keys

// Remember pattern (most common)
Cache::remember('key', 300, fn () => /* expensive operation */);
Cache::rememberForever('key', fn () => /* never expires */);

// Tagged cache
Cache::tags(['tag1', 'tag2'])->put('key', $value, 300);
Cache::tags(['tag1', 'tag2'])->get('key');
Cache::tags(['tag1'])->flush();     // clear all keys with tag1
Cache::tags(['tag1', 'tag2'])->flush(); // clear keys with BOTH tags

// Atomic operations
Cache::increment('visits');             // increment by 1
Cache::increment('visits', 5);         // increment by 5
Cache::decrement('remaining_slots');

// Check existence
Cache::has('key');                      // true if key exists and not expired
Cache::missing('key');                  // true if key does not exist

// Multiple keys at once
Cache::many(['key1', 'key2']);          // get multiple
Cache::putMany(['key1' => $v1, 'key2' => $v2], 300); // store multiple

// Locking (prevents race conditions)
$lock = Cache::lock('invoice-generation-' . $invoiceId, 10);
if ($lock->get()) {
    try {
        // Only one process runs this at a time
        $this->invoiceService->generatePdf($invoice);
    } finally {
        $lock->release();
    }
}
```

---

## Production Fix — TTL and Connection Settings

Add cache TTLs to `config/freelanceflow.php` so service code does not hardcode expiry values:

```php
'cache' => [
    'ttl' => [
        'dashboard_stats' => (int) env('CACHE_TTL_DASHBOARD_STATS', 300),
        'revenue_chart' => (int) env('CACHE_TTL_REVENUE_CHART', 300),
        'project_status' => (int) env('CACHE_TTL_PROJECT_STATUS', 300),
        'recent_activity' => (int) env('CACHE_TTL_RECENT_ACTIVITY', 60),
        'client_stats' => (int) env('CACHE_TTL_CLIENT_STATS', 300),
        'client_record' => (int) env('CACHE_TTL_CLIENT_RECORD', 600),
        'tag_list' => (int) env('CACHE_TTL_TAG_LIST', 3600),
    ],
],
```

Update `.env.example` with production-ready Redis settings:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=default
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_CACHE_CONNECTION=cache
REDIS_CACHE_LOCK_CONNECTION=default
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90
REDIS_PERSISTENT=false
REDIS_MAX_RETRIES=3
REDIS_BACKOFF_ALGORITHM=decorrelated_jitter
REDIS_BACKOFF_BASE=100
REDIS_BACKOFF_CAP=1000
```

For high traffic production, prefer separate Redis databases or managed Redis instances for:

- Cache data
- Queue jobs
- Sessions
- Locks

That separation prevents a busy queue or large cache from evicting sessions.

---

## What We Learned Today

- **Redis vs database cache** — Redis is an in-memory data store. Cache reads hit RAM, not disk. Orders of magnitude faster than MySQL for cache operations
- **`CACHE_STORE=redis`** — switches Laravel's default cache driver to Redis. All existing `Cache::remember()` calls use Redis automatically
- **Separate Redis databases** — `REDIS_DB=0` for application data, `REDIS_CACHE_DB=1` for cache. Lets you flush cache without touching sessions or queue jobs
- **Cache tags** — group related cache entries. `Cache::tags(['dashboard', 'workspace:1'])->flush()` clears all dashboard cache for workspace 1 in one call. Requires Redis or Memcached — the database driver does not support tags
- **Cache tag fallback** — wrap `Cache::tags()` in an app service so local database/file cache does not crash while production still gets Redis tags
- **Workspace-scoped keys** — every cache key should include the workspace ID. Never mix user ID and workspace ID in dashboard cache keys
- **Config-driven TTLs** — keep expiry values in `config/freelanceflow.php`, not scattered through services
- **Model observers** — fire automatically on Eloquent lifecycle events. Bust cache in the observer instead of scattered `bustCache()` calls throughout service methods
- **`Cache::lock()`** — distributed lock backed by Redis. Prevents race conditions when multiple queue workers or requests try to run the same expensive operation simultaneously
- **`redis-cli MONITOR`** — logs every Redis command in real time. Invaluable for debugging cache key names and hit/miss patterns

---

## Day 39 — Cache Strategies

Tomorrow we go deeper on caching patterns. Cache-aside, write-through, read-through, event-driven cache busting — each pattern has a different trade-off between consistency and performance. We will implement the right pattern for each type of data in FreelanceFlow and add cache warming for the dashboard so the first user after a deployment does not wait for cold cache.

See you on Day 39.
