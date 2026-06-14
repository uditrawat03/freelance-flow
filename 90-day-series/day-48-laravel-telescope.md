# Day 48 - Laravel Telescope

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3:** Advanced Operations
> **Read time:** 15 min | **Level:** Intermediate

Horizon tells us what is happening in the queue. Telescope tells us what happened during a request, job, query, cache operation, mail, notification, scheduled command, or exception.

Today we add Laravel Telescope to FreelanceFlow as a local development observability layer. The important part is not just installing the dashboard. We configure it so it stays useful as the app grows: noisy paths are ignored, slow queries are surfaced, storage is pruned, sensitive data is redacted outside local development, and production installs are not forced to load a dev-only package.

---

## What Changed

### New files

- `app/Providers/TelescopeServiceProvider.php`
- `config/telescope.php`
- `database/migrations/2026_06_14_055256_create_telescope_entries_table.php`
- `tests/Feature/TelescopeConfigurationTest.php`

### Modified files

- `composer.json`
- `composer.lock`
- `.env.example`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/providers.php`
- `routes/console.php`
- `app/Jobs/SendProjectNotification.php`
- `app/Jobs/RefreshDashboardCache.php`

---

## Step 1 - Install Telescope

Telescope is a development tool, so install it as a dev dependency:

```bash
composer require laravel/telescope --dev
php artisan telescope:install
```

On Windows, this project already has Horizon installed. Horizon requires Linux process extensions that are not available in normal Laragon PHP builds, so the install may need the same platform ignores used on Day 47:

```bash
composer require laravel/telescope --dev --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
php artisan telescope:install
```

The install command publishes the provider, config file, assets, and Telescope table migration.

Run migrations locally when you are ready to use the dashboard:

```bash
php artisan migrate
```

---

## Step 2 - Register the App Provider Safely

The installer adds `App\Providers\TelescopeServiceProvider` to `bootstrap/providers.php`. That works locally, but it is risky for production because Telescope is in `require-dev`. A `composer install --no-dev` deployment may not contain the `Laravel\Telescope` classes.

So `bootstrap/providers.php` keeps only app-owned core providers:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
];
```

Then `AppServiceProvider` registers the app Telescope provider only when the package exists and the app is local:

```php
public function register(): void
{
    $this->app->bind(ClientRepositoryInterface::class, EloquentClientRepository::class);
    $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
    $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);

    $this->app->singleton(ClientService::class);
    $this->app->singleton(ProjectService::class);
    $this->app->singleton(InvoiceService::class);
    $this->app->singleton(DashboardService::class);
    $this->app->singleton(Logger::class);

    if ($this->app->isLocal() && class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
        $this->app->register(TelescopeServiceProvider::class);
    }
}
```

This keeps Telescope available in local development without making production depend on a dev package.

---

## Step 3 - Protect and Filter Telescope Entries

The published provider now does three jobs:

- Enables Telescope's dark dashboard theme locally
- Redacts sensitive request input and headers outside local development
- Records all local entries, but only important entries in non-local environments

```php
public function register(): void
{
    Telescope::night();

    $this->hideSensitiveRequestDetails();

    Telescope::filter(function (IncomingEntry $entry) {
        if ($this->app->isLocal()) {
            return true;
        }

        return $entry->isReportableException()
            || $entry->isFailedRequest()
            || $entry->isFailedJob()
            || $entry->isScheduledTask()
            || $entry->hasMonitoredTag();
    });
}
```

Sensitive fields are hidden outside local:

```php
Telescope::hideRequestParameters([
    '_token',
    'password',
    'password_confirmation',
    'current_password',
    'two_factor_secret',
    'two_factor_recovery_codes',
]);

Telescope::hideRequestHeaders([
    'authorization',
    'cookie',
    'stripe-signature',
    'x-csrf-token',
    'x-xsrf-token',
]);
```

Dashboard access uses an admin-only gate:

```php
protected function gate(): void
{
    Gate::define('viewTelescope', function (User $user) {
        return $user->hasRole('admin');
    });
}
```

Local development is still open by Telescope's default authorization behavior, but staging or shared environments require an admin user.

---

## Step 4 - Tune Watchers for Scale

The default Telescope config records a lot. That is useful for a tiny app, but noisy once Livewire, queues, cache warming, Horizon, and background work are active.

FreelanceFlow keeps the useful signals and cuts the noisy ones:

```php
'queue' => [
    'connection' => env('TELESCOPE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    'queue' => env('TELESCOPE_QUEUE', 'low'),
    'delay' => env('TELESCOPE_QUEUE_DELAY', 10),
],

'ignore_paths' => [
    '_debugbar*',
    '_boost*',
    '.well-known*',
    'favicon.ico',
    'health',
    'horizon*',
    'livewire*',
    'nova-api*',
    'pulse*',
    'telescope*',
],

'ignore_commands' => [
    'horizon',
    'horizon:*',
    'queue:*',
    'schedule:finish',
    'schedule:run',
    'telescope:prune',
],
```

Watcher highlights:

```php
Watchers\LogWatcher::class => [
    'enabled' => env('TELESCOPE_LOG_WATCHER', true),
    'level' => 'error',
],

Watchers\ModelWatcher::class => [
    'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
    'events' => ['eloquent.created*', 'eloquent.updated*', 'eloquent.deleted*'],
    'hydrations' => false,
],

Watchers\QueryWatcher::class => [
    'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
    'ignore_packages' => true,
    'ignore_paths' => [],
    'slow' => 100,
],

Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', false),
Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', false),
```

The scalability choices are intentional:

- `livewire*` is ignored because Livewire update requests can dominate the dashboard during normal typing.
- Query watcher keeps package queries out and flags queries slower than 100ms.
- Model hydration tracking is disabled because it can become very noisy on list pages.
- Redis and View watchers are off by default; enable them only while debugging those layers.
- Telescope's queued update work goes to the existing `low` queue so it does not compete with user-facing jobs.

---

## Step 5 - Prune Telescope Storage

Telescope stores entries in the database. Without pruning, `telescope_entries`, `telescope_entries_tags`, and `telescope_monitoring` keep growing.

`routes/console.php` now schedules pruning only when Telescope exists:

```php
if (class_exists(\Laravel\Telescope\Telescope::class)) {
    Schedule::command('telescope:prune --hours=48')
        ->daily()
        ->withoutOverlapping()
        ->when(fn () => app()->isLocal() || (bool) config('telescope.enabled'));
}
```

This protects production installs that run without dev dependencies and keeps local databases from bloating.

---

## Step 6 - Environment Flags

`.env.example` documents the useful Telescope knobs:

```env
# Laravel Telescope (local development only; disabled in tests)
TELESCOPE_ENABLED=true
TELESCOPE_PATH=telescope
TELESCOPE_DOMAIN=
TELESCOPE_DRIVER=database
TELESCOPE_QUEUE_CONNECTION=${QUEUE_CONNECTION}
TELESCOPE_QUEUE=low
TELESCOPE_QUEUE_DELAY=10
TELESCOPE_RESPONSE_SIZE_LIMIT=64
TELESCOPE_QUERY_WATCHER=true
TELESCOPE_REQUEST_WATCHER=true
TELESCOPE_CACHE_WATCHER=true
TELESCOPE_JOB_WATCHER=true
TELESCOPE_MAIL_WATCHER=true
TELESCOPE_NOTIFICATION_WATCHER=true
TELESCOPE_EVENT_WATCHER=true
TELESCOPE_EXCEPTION_WATCHER=true
TELESCOPE_MODEL_WATCHER=true
TELESCOPE_REDIS_WATCHER=false
TELESCOPE_VIEW_WATCHER=false
```

The test configuration already disables Telescope:

```xml
<env name="TELESCOPE_ENABLED" value="false"/>
```

That keeps test output and database writes predictable.

---

## Step 7 - Add Useful Job Tags

Telescope tags make operational filtering much easier. Instead of searching through every job, we can filter by workspace, queue, project, client, or job type.

`SendProjectNotification`:

```php
public function tags(): array
{
    return [
        'project:'.$this->project->id,
        'client:'.$this->project->client_id,
        'workspace:'.$this->project->workspace_id,
        'queue:emails',
        'type:project-notification',
    ];
}
```

`RefreshDashboardCache`:

```php
public function tags(): array
{
    return [
        'workspace:'.$this->workspaceId,
        'queue:low',
        'type:dashboard-cache-refresh',
    ];
}
```

These tags become more valuable as data grows. When a workspace reports slow dashboards or missing emails, Telescope can narrow the timeline quickly.

---

## Step 8 - Audit Phase 3 with Telescope

Start the app locally, visit `/telescope`, then move through these checks:

| Area | What to check | Healthy signal |
|---|---|---|
| Dashboard | Requests and Queries | Cache warm-up reduces repeated aggregate queries |
| Project pages | Queries | No N+1 query growth as projects and tags increase |
| Queue jobs | Jobs | Email jobs use `queue:emails`; cache refresh uses `queue:low` |
| Notifications | Notifications and Mail | Notification events are visible without blocking default jobs |
| Exceptions | Exceptions | Reportable exceptions are searchable by request and user context |
| Slow queries | Queries | Anything over 100ms appears in the query watcher |

For Livewire-heavy flows, temporarily remove `livewire*` from `ignore_paths` while debugging, then restore it. Keeping it ignored by default makes Telescope usable day to day.

---

## Step 9 - Test the Configuration

Run the focused observability tests:

```bash
php artisan test tests/Feature/TelescopeConfigurationTest.php tests/Feature/HorizonConfigurationTest.php
```

Run the full suite before committing:

```bash
php artisan test
```

The Telescope test verifies:

- Telescope is installed as a dev dependency
- The app Telescope provider is not statically loaded from `bootstrap/providers.php`
- `TELESCOPE_ENABLED=false` is honored in tests
- noisy paths and commands are ignored
- slow query, model, Redis, and View watcher settings stay scalable
- queue jobs expose searchable Telescope tags

---

## Scalability Checklist

- Keep Telescope in `require-dev`
- Register the app Telescope provider only when the package exists and the app is local
- Disable Telescope in tests
- Ignore Livewire, Horizon, Telescope, health-check, asset, and scheduler noise
- Send Telescope background processing to the `low` queue
- Keep Redis and View watchers off until specifically needed
- Track only create, update, and delete model events by default
- Keep query watcher focused on slow queries and app queries
- Add tags to jobs that matter operationally
- Prune entries every day
- Never expose Telescope publicly without an admin gate

---

## What We Learned Today

- Telescope is a local observability dashboard, not a production monitoring replacement
- Dev-only packages must be registered carefully in Laravel 13 apps
- The best Telescope setup is selective: record the signal, ignore the noise
- Slow query thresholds and job tags make debugging faster as data grows
- Scheduled pruning is not optional once Telescope writes to the database
- Horizon and Telescope complement each other: Horizon explains queue health, Telescope explains execution history

---

## Day 49 Preview - Localization and i18n

Tomorrow we prepare FreelanceFlow for multiple languages. We will extract hardcoded strings, add locale configuration, format dates and currency by locale, and make sure search behaves well with non-ASCII text.
