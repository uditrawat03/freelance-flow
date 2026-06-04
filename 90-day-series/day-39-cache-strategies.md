# Day 39 — Cache Strategies

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 3 — Advanced**
> **Read time:** 14 min · **Level:** Intermediate

---

> *"Redis is in place. Cache tags work. But caching is not just 'store it, read it, forget it after five minutes.' Different data has different consistency requirements. Dashboard stats can be slightly stale. Invoice totals cannot. Client names should update immediately. Today we apply the right caching strategy to each type of data in FreelanceFlow — and add cache warming so the first request after deployment is never slow."*

---

## What We Are Building Today

1. **The four cache strategies** — cache-aside, write-through, read-through, event-driven
2. **Apply the right strategy per data type** in FreelanceFlow
3. **TTL strategy** — different expiry times for different data
4. **Cache warming** — pre-fill cache on deployment so cold starts never hit users
5. **Cache warming command** — `php artisan cache:warm`
6. **Stale-while-revalidate** — serve stale data while refreshing in the background
7. **Cache debugging** — hit/miss rates, key inspection

---

## The Four Cache Strategies

### Strategy 1 — Cache-Aside (Lazy Loading)

The application checks the cache first. On a miss, it fetches from the database, stores in cache, and returns the result. This is what `Cache::remember()` does.

```php
// Cache-aside — the most common pattern in Laravel
$stats = Cache::tags(['dashboard', "workspace:{$workspaceId}"])
    ->remember("stats_{$workspaceId}", 300, function () {
        // Only runs on cache miss
        return $this->fetchStatsFromDatabase();
    });
```

**When to use:** Most dashboard aggregates, report data, stats that are expensive to compute and can tolerate a few minutes of staleness.

**Trade-off:** The first request after cache expiry is slow. Every other request is instant.

---

### Strategy 2 — Write-Through

Update the cache at the same time you write to the database. No cache miss is possible — the cache always has fresh data.

```php
// Write-through — update cache and database together
public function update(Client $client, array $data): Client
{
    $updated = $this->clients->update($client, $data);

    // Write the updated model directly into cache — no miss possible
    Cache::tags(['clients', "workspace:{$client->workspace_id}"])
        ->put(
            "client_{$client->id}",
            $updated,
            600
        );

    return $updated;
}

// Read uses the same key
public function findCached(int $id): ?Client
{
    $workspaceId = auth()->user()->currentWorkspace()?->id;

    return Cache::tags(['clients', "workspace:{$workspaceId}"])
        ->remember("client_{$id}", 600, fn () => $this->clients->find($id));
}
```

**When to use:** Frequently read single records (client detail page, project show). The client show page is visited after every edit — write-through means it is always fresh.

**Trade-off:** Every write is slightly slower (cache + database). Worth it for frequently read records.

---

### Strategy 3 — Event-Driven Cache Busting

Do not expire by TTL at all. Cache indefinitely and bust when the data changes. The model observer we built on Day 38 is exactly this pattern.

```php
// Cache indefinitely (no TTL)
Cache::tags(['clients', "workspace:{$workspaceId}"])
    ->rememberForever("client_list_{$workspaceId}", fn () => $this->clients->activeClients());

// Bust when data changes — handled by ClientObserver
public function updated(Client $client): void
{
    Cache::tags(['clients', "workspace:{$client->workspace_id}"])->flush();
}
```

**When to use:** Reference data that changes infrequently — tag lists, workspace settings, user preferences. The tag list on Day 17 is a perfect candidate — tags rarely change but are read on every project create/edit form.

**Trade-off:** Cache never expires on its own. Bugs in the busting logic mean stale data lives forever. Always pair with a fallback TTL.

---

### Strategy 4 — Stale-While-Revalidate

Serve the stale cached value immediately. Kick off a background job to refresh it. The next request gets the fresh value.

```php
// Stale-while-revalidate using a queue job
public function stats(): array
{
    $workspaceId = auth()->user()->currentWorkspace()?->id;
    $cacheKey    = "stats_{$workspaceId}";
    $staleTtl    = 300;   // 5 minutes
    $graceWindow = 60;    // 1 minute grace period

    $cached = Cache::tags(['dashboard', "workspace:{$workspaceId}"])->get($cacheKey);

    if ($cached !== null) {
        $age = Cache::tags(['dashboard', "workspace:{$workspaceId}"])
            ->get("{$cacheKey}_updated_at");

        // If stale but within grace window — serve stale, refresh in background
        if ($age && now()->diffInSeconds($age) > $staleTtl) {
            \App\Jobs\RefreshDashboardCache::dispatch($workspaceId)
                ->onQueue('low');
        }

        return $cached;
    }

    // Complete miss — compute synchronously
    return $this->fetchAndCacheStats($workspaceId);
}
```

**When to use:** Dashboard data where the 5-minute wait on cache expiry is unacceptable but absolute freshness is not required.

**Trade-off:** Complexity. Only worth implementing when cache-aside TTL causes visible slowness in the UI.

---

## Applying Strategies to FreelanceFlow Data

| Data | Strategy | TTL | Reason |
|---|---|---|---|
| Dashboard stats | Cache-aside | 5 min | Expensive queries, slight staleness acceptable |
| Revenue chart | Cache-aside | 5 min | Same — computed from many invoice rows |
| Client list (paginated) | Cache-aside | 2 min | Changes often, staleness noticeable |
| Individual client record | Write-through | 10 min | Read after every edit, must be fresh |
| Tag list | Event-driven | Forever | Changes rarely, bust on tag create/update |
| Project status breakdown | Cache-aside | 5 min | Dashboard chart — same as stats |
| Invoice totals | No cache | N/A | Financial data — always read from DB |
| Recent activity feed | Cache-aside | 1 min | Changes frequently, short TTL is fine |
| Workspace settings | Event-driven | Forever | Changes very rarely |

Update `DashboardService` to implement different TTLs per data type:

```php
public function stats(): array
{
    return $this->cache()->remember(
        "stats_{$this->workspaceId()}",
        300, // 5 minutes
        fn () => $this->fetchStats()
    );
}

public function recentActivity(): array
{
    return Cache::remember(
        "recent_activity_{$this->workspaceId()}",
        60, // 1 minute — changes frequently
        fn () => $this->fetchRecentActivity()
    );
}
```

Add a forever-cached tag list to `TagRepository`:

```php
// app/Repositories/Eloquent/EloquentTagRepository.php (new)
public function allCached(): Collection
{
    $workspaceId = auth()->user()->currentWorkspace()?->id;

    return Cache::tags(['tags', "workspace:{$workspaceId}"])
        ->rememberForever(
            "all_tags_{$workspaceId}",
            fn () => Tag::orderBy('name')->get()
        );
}
```

Bust it in a `TagObserver`:

```bash
php artisan make:observer TagObserver --model=Tag
```

```php
<?php

namespace App\Observers;

use App\Models\Tag;
use Illuminate\Support\Facades\Cache;

class TagObserver
{
    public function saved(Tag $tag): void
    {
        // 'saved' covers both created and updated
        Cache::tags(['tags'])->flush();
    }

    public function deleted(Tag $tag): void
    {
        Cache::tags(['tags'])->flush();
    }
}
```

Register in `AppServiceProvider`:

```php
use App\Models\Tag;
use App\Observers\TagObserver;

Tag::observe(TagObserver::class);
```

Now use `allCached()` in the project create/edit forms instead of `Tag::orderBy('name')->get()`:

```php
// app/Livewire/Projects/Create.php
public function render()
{
    return view('livewire.projects.create', [
        'clients' => Client::active()->orderBy('name')->get(),
        'tags'    => app(\App\Repositories\Contracts\TagRepositoryInterface::class)->allCached(),
    ]);
}
```

---

## Dashboard Period Switching

The dashboard period selector should not manually forget a revenue chart key. Each period has its own workspace-scoped key:

```php
dashboard:revenue_chart:{workspaceId}:3
dashboard:revenue_chart:{workspaceId}:6
dashboard:revenue_chart:{workspaceId}:12
```

So the Livewire component only needs to re-render with the selected period:

```php
public function updatedPeriod(): void
{
    // No manual Cache::forget() here.
    // Switching periods reads the correct workspace-scoped cache key.
}
```

Manual invalidation belongs in observers and service methods that know data changed. User interface state changes should select a key, not delete unrelated cache entries.

---

## Cache Warming Command

Cold cache after a deployment means the first user hits all the slow database queries. A warm-up command pre-fills the cache before anyone visits.

For production, the command must be:

- Workspace-aware
- Safe to run more than once
- Protected by Redis locks
- Able to continue when one workspace fails
- Careful to restore auth and session state after each workspace

```bash
php artisan make:command WarmCache
```

```php
<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WarmCache extends Command
{
    protected $signature = 'cache:warm
                            {--workspace= : Warm cache for a specific workspace ID}';

    protected $description = 'Pre-fill the Redis cache with dashboard data for all workspaces.';

    public function handle(DashboardService $dashboardService): int
    {
        $workspaces = Workspace::with('owner')
            ->when($this->option('workspace'), fn ($q) => $q->where('id', $this->option('workspace')))
            ->get();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($workspaces->count());
        $bar->start();

        foreach ($workspaces as $workspace) {
            $owner = $workspace->owner;

            if (! $owner) {
                Log::warning('Skipping cache warm because workspace has no owner.', [
                    'workspace_id' => $workspace->id,
                ]);

                $bar->advance();
                continue;
            }

            $lock = Cache::lock("cache_warm_workspace_{$workspace->id}", 300);

            if (! $lock->get()) {
                $this->warn("\n  Skipped: {$workspace->name} is already warming.");
                $bar->advance();
                continue;
            }

            try {
                // Simulate auth context so tenant-aware scopes resolve correctly.
                auth()->guard('web')->login($owner);
                session(['current_workspace_id' => $workspace->id]);

                $dashboardService->stats();
                $dashboardService->revenueChart(3);
                $dashboardService->revenueChart(6);
                $dashboardService->revenueChart(12);
                $dashboardService->projectStatusBreakdown();

                $this->line("\n  ✓ Warmed: {$workspace->name}");
            } catch (\Throwable $e) {
                $this->warn("\n  ✗ Failed: {$workspace->name} — {$e->getMessage()}");
                Log::warning('Cache warm failed.', [
                    'workspace_id' => $workspace->id,
                    'exception' => $e,
                ]);
            } finally {
                auth()->guard('web')->logout();
                session()->forget('current_workspace_id');
                $lock->release();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Cache warm-up complete.');

        return self::SUCCESS;
    }
}
```

> If `CACHE_STORE=database` locally, the lock still works through Laravel's cache lock support. In production, Redis gives the lock shared visibility across all web and queue workers.

Add to the scheduler in `routes/console.php`:

```php
// Warm cache after every deployment — triggered by CI/CD
// Also runs daily at 5am before the work day starts
Schedule::command('cache:warm')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();
```

And call it explicitly in your deployment pipeline:

```bash
# In your CI/CD deploy script (after migrate and optimize)
php artisan optimize
php artisan cache:warm
```

---

## RefreshDashboardCache Job (Stale-While-Revalidate)

```bash
php artisan make:job RefreshDashboardCache
```

```php
<?php

namespace App\Jobs;

use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshDashboardCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $workspaceId,
    ) {}

    public function handle(DashboardService $dashboardService): void
    {
        $workspace = Workspace::with('owner')->find($this->workspaceId);

        if (! $workspace || ! $workspace->owner) {
            return;
        }

        $lock = Cache::lock("refresh_dashboard_cache_{$workspace->id}", 300);

        if (! $lock->get()) {
            return;
        }

        try {
            // Set auth context for tenant-aware global scopes.
            auth()->guard('web')->login($workspace->owner);
            session(['current_workspace_id' => $workspace->id]);

            $dashboardService->bustCache();
            $dashboardService->stats();
            $dashboardService->revenueChart(3);
            $dashboardService->revenueChart(6);
            $dashboardService->revenueChart(12);
            $dashboardService->projectStatusBreakdown();
            $dashboardService->recentActivity();
        } catch (\Throwable $e) {
            Log::warning('Dashboard cache refresh failed.', [
                'workspace_id' => $workspace->id,
                'exception' => $e,
            ]);

            throw $e;
        } finally {
            auth()->guard('web')->logout();
            session()->forget('current_workspace_id');
            $lock->release();
        }
    }
}
```

The lock prevents multiple workers from rebuilding the same workspace dashboard at once. The `finally` block prevents a failed job from leaking one workspace's auth/session context into the next job handled by the same worker.

---

## Cache Debugging — Hit and Miss Rates

Add a simple cache hit/miss counter to understand how effective the cache is:

```php
// app/Http/Middleware/TrackCachePerformance.php (development only)
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
```

Register only in local environment:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    if (app()->isLocal()) {
        $middleware->web(append: [
            \App\Http\Middleware\TrackCachePerformance::class,
        ]);
    }
})
```

Now every request logs cache hit/miss to `storage/logs/laravel.log`. A well-cached dashboard page should show 90%+ hit rate after the first visit.

---

## Cache Strategy Summary for FreelanceFlow

```php
// config/freelanceflow.php additions
'cache' => [
    'ttl' => [
        'dashboard_stats' => (int) env('CACHE_TTL_DASHBOARD_STATS', 300),
        'revenue_chart' => (int) env('CACHE_TTL_REVENUE_CHART', 300),
        'project_status' => (int) env('CACHE_TTL_PROJECT_STATUS', 300),
        'client_list' => (int) env('CACHE_TTL_CLIENT_LIST', 120),
        'client_stats' => (int) env('CACHE_TTL_CLIENT_STATS', 300),
        'client_record' => (int) env('CACHE_TTL_CLIENT_RECORD', 600),
        'recent_activity' => (int) env('CACHE_TTL_RECENT_ACTIVITY', 60),
        'tag_list' => (int) env('CACHE_TTL_TAG_LIST', 3600),
        'workspace_settings' => (int) env('CACHE_TTL_WORKSPACE_SETTINGS', 3600),
    ],
],
```

Use these in services:

```php
Cache::remember('key', config('freelanceflow.cache.ttl.dashboard_stats'), fn () => ...);
Cache::rememberForever('key', fn () => ...); // for TTL = 0 entries
```

---

## What We Learned Today

- **Cache-aside** — `Cache::remember()`. The most common pattern. Lazy — populates on first miss. Best for aggregates and stats
- **Write-through** — update cache and database together. No miss possible after first write. Best for frequently read single records
- **Event-driven busting** — `rememberForever()` + observer `flush()`. Best for reference data that rarely changes (tags, workspace settings)
- **Stale-while-revalidate** — serve stale immediately, refresh in background via a queued job. Best when cache-miss latency is user-visible
- **Different TTLs for different data** — dashboard stats: 5 min, recent activity: 1 min, tag list: forever. Tune per data type
- **Cache warming** — `php artisan cache:warm` pre-fills all workspace caches. Run in CI/CD after deployment so cold cache never hits a real user
- **Redis locks** — wrap expensive cache warm and refresh work so only one worker rebuilds a workspace cache at a time
- **Auth/session cleanup** — always clear workspace auth context in `finally` blocks inside cache warming commands and queue jobs
- **Dashboard period switching** — switching chart periods should read a different workspace key, not forget a user-scoped key
- **`RefreshDashboardCache` job** — queued on the `low` priority queue. Refreshes cache in the background without blocking the request
- **Cache hit/miss tracking** — `CacheHit` and `CacheMissed` events. Listen to them in development to measure effectiveness
- **TTL in config** — `config('freelanceflow.cache.ttl.dashboard_stats')`. Never hardcode TTL values in service methods

---

## Day 40 — Real-Time with Reverb

Tomorrow FreelanceFlow gets WebSockets. When a project status changes, the dashboard updates in real time — no page refresh, no polling. We will install Laravel Reverb, configure broadcasting channels, build a private channel for workspace events, and push live updates to the FreelanceFlow dashboard using Livewire's `#[On]` attribute and Echo.js.

See you on Day 40.
