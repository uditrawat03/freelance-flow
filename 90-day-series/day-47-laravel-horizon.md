# Day 47 - Laravel Horizon

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3:** Advanced Operations
> **Read time:** 16 min | **Level:** Intermediate

Running `php artisan queue:work` is fine while a project is small, but it gives very little operational visibility. You cannot quickly see which queue is backing up, which jobs are slow, whether workers are alive, or how retry volume changes over time.

Today we add Laravel Horizon to FreelanceFlow so queued work becomes observable, scalable, and separated by priority.

---

## What Changed

### New files

- `config/horizon.php`
- `tests/Feature/HorizonConfigurationTest.php`

### Modified files

- `composer.json`
- `composer.lock`
- `.env.example`
- `app/Jobs/SendProjectNotification.php`
- `app/Jobs/RefreshDashboardCache.php`
- `app/Listeners/SendProjectNotification.php`
- `app/Mail/ProjectCreated.php`
- `app/Mail/InvoicePaymentReminder.php`
- `app/Mail/MonthlyRevenueReport.php`
- `app/Notifications/ProjectStatusChanged.php`
- `app/Notifications/InvoiceOverdue.php`
- `app/Providers/AppServiceProvider.php`
- `routes/console.php`
- `app/Console/Commands/SendInvoiceReminders.php`
- `app/Console/Commands/GenerateMonthlyRevenueReport.php`

---

## Step 1 - Install Horizon

```bash
composer require laravel/horizon
php artisan horizon:install
```

On Windows, Horizon's package requires the Linux process extensions `pcntl` and `posix`. Laragon usually does not ship those extensions, so local installation may need:

```bash
composer require laravel/horizon --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

That is acceptable for developing and testing configuration on Windows, but the actual `php artisan horizon` process should run in Linux, Docker, WSL, or production infrastructure where those extensions are available.

---

## Step 2 - Configure Scalable Queue Pools

`config/horizon.php` now defines four queue lanes:

| Queue | Supervisor | Purpose |
|---|---|---|
| `default` | `supervisor-default` | General app jobs |
| `emails` | `supervisor-emails` | Mail delivery and notification mail |
| `notifications` | `supervisor-notifications` | Database/mail notification jobs |
| `low` | `supervisor-low` | Cache warming, reports, and long low-priority work |

The important production settings are:

```php
'supervisor-default' => [
    'connection' => 'redis',
    'queue' => ['default'],
    'balance' => 'auto',
    'autoScalingStrategy' => 'time',
    'minProcesses' => (int) env('HORIZON_DEFAULT_MIN_PROCESSES', 1),
    'maxProcesses' => (int) env('HORIZON_DEFAULT_MAX_PROCESSES', 5),
],
```

Email and notification supervisors also use `balance: auto`, so Horizon can add workers when wait time grows. The `low` supervisor intentionally stays small and uses `nice => 10`, giving background work lower CPU priority.

This queue isolation is the scalability win: a slow SMTP provider should not block dashboard cache refreshes, and a heavy report should not delay project notifications.

---

## Step 3 - Route Jobs to Dedicated Queues

`SendProjectNotification` now runs on the `emails` queue:

```php
public function __construct(
    public readonly Project $project,
) {
    $this->onQueue('emails');
}
```

`RefreshDashboardCache` now runs on the `low` queue and guarantees auth/session cleanup with `finally`:

```php
public int $tries = 2;
public int $timeout = 300;

public function __construct(public readonly int $workspaceId)
{
    $this->onQueue('low');
}
```

The `finally` block matters for long-running workers. Horizon keeps PHP processes alive across jobs, so leaked auth/session state can cause subtle cross-job bugs.

---

## Step 4 - Route Mail and Notifications

Queued mailables are assigned to `emails`:

```php
public function __construct(public readonly Invoice $invoice)
{
    $this->onQueue('emails');
}
```

Notifications are assigned to `notifications`:

```php
public function __construct(
    public readonly Project $project,
    public readonly string $previousStatus,
) {
    $this->onQueue('notifications');
}
```

The mailables do not blindly implement `ShouldQueue` here. Some application code already sends mail from inside a queued job, so the job remains the async boundary and the mailable queue name applies when the mailable is queued directly with `queue()` or `later()`.

---

## Step 5 - Protect the Horizon Dashboard

`AppServiceProvider` authorizes Horizon like this:

```php
Horizon::auth(function ($request): bool {
    if (app()->isLocal()) {
        return true;
    }

    return $request->user()?->hasRole('admin') ?? false;
});
```

Horizon exposes job payloads, exception traces, tags, queue names, and timing data. Treat it like an admin-only operations dashboard.

---

## Step 6 - Add Scheduler Hooks

`routes/console.php` now records Horizon metrics:

```php
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->when(fn () => app()->bound('horizon'))
    ->withoutOverlapping();
```

Production also logs a critical error when no Horizon master supervisor is visible:

```php
Schedule::call(function () {
    $masters = app(MasterSupervisorRepository::class)->all();

    if (empty($masters)) {
        Log::critical('Horizon is not running.');
    }
})->everyFiveMinutes();
```

In a real production setup, route this critical log to Sentry, Slack, PagerDuty, or your alerting system.

---

## Step 7 - Environment Settings

`.env.example` now includes Horizon worker knobs:

```env
QUEUE_CONNECTION=database

HORIZON_NAME="${APP_NAME}"
HORIZON_PATH=horizon
HORIZON_DOMAIN=
HORIZON_PREFIX=
HORIZON_REDIS_CONNECTION=default
HORIZON_DEFAULT_MIN_PROCESSES=1
HORIZON_DEFAULT_MAX_PROCESSES=5
HORIZON_EMAIL_MIN_PROCESSES=1
HORIZON_EMAIL_MAX_PROCESSES=3
HORIZON_NOTIFICATION_MIN_PROCESSES=1
HORIZON_NOTIFICATION_MAX_PROCESSES=3
HORIZON_LOW_PROCESSES=1
```

For production, set:

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

Horizon only manages Redis queues. Database queues can still work locally, but Horizon is designed for Redis-backed production workers.

---

## Step 8 - Run Horizon

```bash
php artisan horizon
```

Useful commands:

```bash
php artisan horizon:status
php artisan horizon:pause
php artisan horizon:continue
php artisan horizon:terminate
```

Use `horizon:terminate` during deployments. It lets the current Horizon master exit gracefully so your process manager can start a fresh copy with the new code.

---

## Step 9 - Production Process Manager

Use Supervisor, systemd, Forge, Vapor, Docker, or another process manager to keep Horizon alive.

Example Supervisor config:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/freelanceflow/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/freelanceflow/storage/logs/horizon.log
stopwaitsecs=3600
```

`stopwaitsecs` should be longer than the longest legitimate job timeout. Our low-priority cache job can run for up to 300 seconds, so one hour is comfortably safe.

---

## Step 10 - Test the Queue Topology

Run the focused test:

```bash
php artisan test tests/Feature/HorizonConfigurationTest.php
```

Run the full suite:

```bash
php artisan test
```

The new test verifies:

- Horizon has separate supervisors for `default`, `emails`, `notifications`, and `low`
- high-volume queues use auto balancing
- low-priority work runs with lower OS priority
- jobs, mailables, and notifications are assigned to the intended queues

---

## Scalability Checklist

- Use Redis for production queues
- Keep email, notification, default, and low-priority jobs isolated
- Tune worker counts with environment variables, not code edits
- Keep long-running jobs on dedicated low-priority workers
- Use `finally` blocks when jobs temporarily change auth/session/global context
- Run `horizon:snapshot` every five minutes for metrics
- Protect `/horizon` with admin-only access outside local
- Restart deployments with `php artisan horizon:terminate`
- Monitor critical logs for missing Horizon supervisors

---

## What We Learned Today

- Horizon turns Laravel queues into an observable worker system
- Queue isolation prevents one slow workload from starving the rest of the app
- Auto balancing lets worker pools grow and shrink with demand
- Long-running workers make cleanup discipline more important
- Horizon should be treated as production operations infrastructure, not just a prettier `queue:work`

---

## Day 48 Preview - Laravel Telescope

Tomorrow we add Laravel Telescope for local development debugging. Horizon answers "what is happening in the queue?" Telescope answers "what happened during this request, job, mail, query, notification, or exception?"
