# Day 35 — Logging & Error Tracking

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 14 min · **Level:** Intermediate

---

> *"In production, errors happen silently. A payment fails, a PDF generation crashes, a queued job dies — and no one knows until a client complains. Today we add proper observability to FreelanceFlow: structured logging with context, multiple log channels, Sentry for real-time error tracking, and Slack alerts for critical failures. Nothing goes unnoticed."*

---

## What We Are Building Today

1. **Structured logging** — log entries with workspace ID, user ID, and request context
2. **Log channels** — separate files for different concerns
3. **Sentry integration** — real-time error tracking in production
4. **Slack alerts** — critical errors go to a Slack channel immediately
5. **A `Logger` service** — consistent structured log calls across the app
6. **Log levels** — using the right level for the right situation

---

## Step 1 — Understanding Laravel Log Channels

Laravel supports multiple log channels configured in `config/logging.php`. Each channel writes to a different destination — a file, Slack, Sentry, or the console.

Open `config/logging.php` and update it:

```php
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        // Stack — writes to multiple channels at once
        'stack' => [
            'driver'            => 'stack',
            'channels'          => ['daily', 'slack-critical'],
            'ignore_exceptions' => false,
        ],

        // Daily rotating files — 30 days retention
        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => 30,
            'replace_placeholders' => true,
        ],

        // Separate channel for payment-related events
        'payments' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/payments.log'),
            'level'  => 'info',
            'days'   => 90,  // keep payment logs longer for compliance
        ],

        // Separate channel for queue jobs
        'queue' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/queue.log'),
            'level'  => 'info',
            'days'   => 14,
        ],

        // Slack — only critical errors
        'slack-critical' => [
            'driver'   => 'slack',
            'url'      => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'FreelanceFlow',
            'emoji'    => ':fire:',
            'level'    => 'critical',
        ],

        // Sentry — all errors and above in production
        'sentry' => [
            'driver' => 'sentry',
            'level'  => env('LOG_LEVEL', 'error'),
            'bubble' => true,
        ],

        // Null — swallows all logs (useful in tests)
        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Single file — simple, no rotation
        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        // Emergency — logs to php error_log as last resort
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
```

Update `.env`:

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/your/webhook/url
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
```

---

## Step 2 — Install Sentry

```bash
composer require sentry/sentry-laravel
```

Publish the Sentry config:

```bash
php artisan sentry:publish --dsn=https://your-dsn@sentry.io/project-id
```

This creates `config/sentry.php` and adds Sentry to the `logging.php` channels automatically. Open `config/sentry.php` and configure it for FreelanceFlow:

```php
<?php

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release'     => env('SENTRY_RELEASE'),
    'environment' => env('APP_ENV', 'production'),

    // Only capture errors in production and staging
    // Never in local development
    'capture_default_pii' => false,

    'traces_sample_rate'   => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'breadcrumbs' => [
        'logs'            => true,
        'cache'           => false,
        'livewire'        => true,
        'sql_queries'     => true,
        'sql_bindings'    => false,
        'queue_info'      => true,
        'command_info'    => true,
        'http_client_requests'       => true,
        'notifications'              => false,
    ],

    'tracing' => [
        'queue_job_transactions'         => true,
        'queue_jobs'                     => true,
        'sql_queries'                    => true,
        'sql_origin'                     => true,
        'views'                          => false,
        'livewire'                       => true,
        'http_client_requests'           => true,
        'redis_commands'                 => false,
        'redis_origin'                   => false,
        'queue_job_try_attempt'          => false,
        'missing_routes'                 => false,
    ],

];
```

Add the Sentry channel to your stack in `config/logging.php`:

```php
'stack' => [
    'driver'   => 'stack',
    'channels' => ['daily', 'slack-critical', 'sentry'],
],
```

Test that Sentry is configured:

```bash
php artisan sentry:test
```

This throws a test exception and you should see it appear in your Sentry dashboard within seconds.

---

## Step 3 — Structured Logging with Context

Raw log calls like `Log::info('Invoice created')` are not very useful in production. You need context — which workspace, which user, which invoice — to debug a problem.

Create a dedicated `Logger` service class `app/Services/Logger.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class Logger
{
    /**
     * Build the base context array attached to every log entry.
     * Includes workspace ID, user ID, and request metadata.
     */
    private function baseContext(): array
    {
        $context = [
            'environment' => app()->environment(),
        ];

        if (auth()->check()) {
            $context['user_id']       = auth()->id();
            $context['user_email']    = auth()->user()->email;
            $context['workspace_id']  = auth()->user()->currentWorkspace()?->id;
        }

        if (request()->exists('url')) {
            $context['url']    = request()->fullUrl();
            $context['method'] = request()->method();
            $context['ip']     = request()->ip();
        }

        return $context;
    }

    public function info(string $message, array $context = []): void
    {
        Log::info($message, array_merge($this->baseContext(), $context));
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge($this->baseContext(), $context));
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, array_merge($this->baseContext(), $context));
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, array_merge($this->baseContext(), $context));
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, array_merge($this->baseContext(), $context));
    }

    // Log to a specific channel
    public function channel(string $channel): \Illuminate\Log\LogManager
    {
        return Log::channel($channel);
    }

    // Payment-specific logging to the dedicated payments channel
    public function payment(string $message, array $context = []): void
    {
        Log::channel('payments')->info($message, array_merge($this->baseContext(), $context));
    }

    // Queue job logging to the dedicated queue channel
    public function queue(string $message, array $context = []): void
    {
        Log::channel('queue')->info($message, array_merge($this->baseContext(), $context));
    }
}
```

Bind the logger as a singleton:

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(\App\Services\Logger::class);
```

---

## Step 4 — Use Structured Logging Across FreelanceFlow

Replace raw `Log::info()` calls with the injected `Logger` service:

```php
// app/Jobs/SendProjectNotification.php
use App\Services\Logger;

class SendProjectNotification implements ShouldQueue
{
    public function handle(Logger $logger): void
    {
        $this->project->loadMissing('client');

        if (! $this->project->client?->email) {
            $logger->warning('SendProjectNotification skipped — client has no email', [
                'project_id' => $this->project->id,
            ]);
            return;
        }

        Mail::to($this->project->client->email)
            ->send(new ProjectCreated($this->project));

        $logger->info('Project notification sent', [
            'project_id' => $this->project->id,
            'to'         => $this->project->client->email,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        // Use static Log here since Logger requires the container
        Log::error('SendProjectNotification failed permanently', [
            'project_id' => $this->project->id,
            'error'      => $exception->getMessage(),
            'trace'      => $exception->getTraceAsString(),
        ]);
    }
}
```

```php
// app/Http/Controllers/StripeWebhookController.php
use App\Services\Logger;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $invoice) {
            $this->logger->payment('Payment succeeded but no matching invoice found', [
                'payment_intent_id' => $paymentIntent->id,
                'amount'            => $paymentIntent->amount / 100,
            ]);
            return;
        }

        $invoice->markAsPaid();

        $this->logger->payment('Invoice marked as paid via Stripe webhook', [
            'invoice_id'        => $invoice->id,
            'invoice_number'    => $invoice->number,
            'payment_intent_id' => $paymentIntent->id,
            'amount'            => $paymentIntent->amount / 100,
        ]);
    }
}
```

---

## Step 5 — Global Exception Handler

Open `bootstrap/app.php` and add structured error logging for all unhandled exceptions:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use App\Services\Logger;

->withExceptions(function (Exceptions $exceptions) {

    // Log all unhandled exceptions with full context
    $exceptions->report(function (\Throwable $e) {
        // Skip reporting common non-critical exceptions
        if ($e instanceof \Illuminate\Auth\AuthenticationException
            || $e instanceof \Illuminate\Validation\ValidationException
            || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
            || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return false; // do not report these to Sentry
        }

        // Add FreelanceFlow context to every Sentry error
        if (app()->bound('sentry') && auth()->check()) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                $scope->setUser([
                    'id'    => auth()->id(),
                    'email' => auth()->user()->email,
                ]);
                $scope->setTag('workspace_id', auth()->user()->currentWorkspace()?->id);
                $scope->setTag('environment', app()->environment());
            });
        }
    });

    // JSON error responses for API routes (from Day 25)
    $exceptions->render(function (\Throwable $e, $request) {
        if ($request->is('api/*')) {
            return match (true) {
                $e instanceof \Illuminate\Auth\AuthenticationException =>
                    response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401),
                $e instanceof \Illuminate\Auth\Access\AuthorizationException =>
                    response()->json(['success' => false, 'message' => 'Forbidden.'], 403),
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException =>
                    response()->json(['success' => false, 'message' => 'Resource not found.'], 404),
                $e instanceof \Illuminate\Validation\ValidationException =>
                    response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors'  => $e->errors(),
                    ], 422),
                default => response()->json([
                    'success' => false,
                    'message' => app()->isProduction() ? 'An unexpected error occurred.' : $e->getMessage(),
                ], 500),
            };
        }
    });

})
```

---

## Step 6 — Log Levels Reference

Use the right log level for each situation. Log levels are hierarchical — setting `LOG_LEVEL=warning` captures warning, error, critical, and alert, but not info or debug.

```php
// DEBUG — detailed development information, turned off in production
Log::debug('Query executed', ['sql' => $query, 'bindings' => $bindings]);

// INFO — normal application events, worth recording
Log::info('Invoice created', ['invoice_id' => $invoice->id, 'total' => $invoice->total]);
Log::info('User logged in', ['user_id' => auth()->id()]);

// WARNING — something unexpected but not breaking
Log::warning('Payment reminder skipped — no email on client', ['client_id' => $client->id]);
Log::warning('Slow query detected', ['duration_ms' => $duration, 'sql' => $sql]);

// ERROR — something failed, needs attention
Log::error('Stripe webhook processing failed', ['error' => $e->getMessage()]);
Log::error('PDF generation failed', ['invoice_id' => $invoice->id]);

// CRITICAL — system is in a bad state, alert the team immediately
Log::critical('Database connection lost', ['connection' => $connection]);
Log::critical('Queue worker crashed', ['worker_id' => $workerId]);

// EMERGENCY — system is unusable
Log::emergency('Application is completely down');
```

---

## Step 7 — Environment-Specific Logging

```env
# Local development — debug everything, no Slack or Sentry
LOG_CHANNEL=daily
LOG_LEVEL=debug

# Staging — info and above, Sentry on, no Slack
LOG_CHANNEL=stack
LOG_LEVEL=info
SENTRY_LARAVEL_DSN=https://...

# Production — warning and above, Sentry + Slack critical
LOG_CHANNEL=stack
LOG_LEVEL=warning
SENTRY_LARAVEL_DSN=https://...
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/...
```

In tests, use the `null` channel so no logs pollute test output:

```env
# .env.testing
LOG_CHANNEL=null
```

---

## Step 8 — Useful Log Helpers

```php
// Write to a specific channel
Log::channel('payments')->info('Payment received', [...]);
Log::channel('queue')->warning('Job retrying', [...]);

// Write to multiple channels at once
Log::stack(['daily', 'slack-critical'])->critical('System failure', [...]);

// Contextual logging — attach context to all subsequent logs in the request
Log::withContext([
    'request_id'   => request()->header('X-Request-Id', uniqid()),
    'workspace_id' => auth()->user()?->currentWorkspace()?->id,
]);

// Then all logs in this request automatically include that context
Log::info('Client list fetched');
// → logged with request_id and workspace_id automatically

// Check current log level
Log::getLogger()->isHandling(\Monolog\Level::Debug);

// Formatters — JSON log output (useful for log aggregation tools)
// Set in config/logging.php per channel:
'formatter' => \Monolog\Formatter\JsonFormatter::class,
```

---

## What We Learned Today

- **Log channels** — separate destinations for different concerns. Payments go to `payments.log`, queue jobs to `queue.log`, critical errors to Slack, everything to Sentry
- **Stack channel** — writes to multiple channels simultaneously with one `Log::info()` call
- **Sentry** — `composer require sentry/sentry-laravel`. Real-time error tracking with stack traces, breadcrumbs, and user context. Test with `php artisan sentry:test`
- **Structured logging** — always include context. `user_id`, `workspace_id`, `invoice_id` — whatever makes the log entry debuggable without needing to reproduce the problem
- **`Logger` service** — centralises base context injection so every log entry automatically includes user and workspace without repeating the code
- **`\Sentry\configureScope()`** — attaches user and workspace context to every Sentry error so errors can be filtered by user or workspace in the Sentry dashboard
- **`$exceptions->report()`** — the hook for customising which exceptions are reported and what context is attached
- **Log levels** — debug for development noise, info for normal events, warning for unexpected but non-breaking, error for failures, critical for system-level problems requiring immediate attention
- **`LOG_CHANNEL=null` in tests** — prevents test output from being polluted with log entries

---

## Day 36 — Config & Environment Management

Tomorrow we go deep on configuration management. How to properly organise environment variables, how to use `config()` correctly versus `env()`, how to manage secrets securely in different environments, and how to prepare the FreelanceFlow configuration for the production deployment that is coming in Phase 4.

See you on Day 36.