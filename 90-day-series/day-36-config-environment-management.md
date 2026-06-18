# Day 36 — Config & Environment Management

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 14 min · **Level:** Intermediate

---

> *"FreelanceFlow has grown to the point where it touches Stripe, Sentry, Mailpit, MySQL, Redis, and Slack. Each one needs credentials, URLs, and options that differ between local, staging, and production. Today we organise all of that properly — separating concerns into config files, never calling env() outside of config, managing secrets safely, and documenting every variable so onboarding a new developer takes minutes not hours."*

---

## What We Are Building Today

1. **The correct `env()` vs `config()` distinction** — and why it matters
2. **Custom config files** for FreelanceFlow-specific settings
3. **Environment-specific configuration** — local, testing, staging, production
4. **Secret management** — what never goes in version control
5. **A complete `.env.example`** — the canonical reference for every variable
6. **Config caching** — how Laravel optimises config in production
7. **Runtime config validation** — fail fast if required variables are missing

---

## Step 1 — env() vs config() — The Critical Distinction

This is the most commonly misunderstood rule in Laravel configuration.

**The rule: never call `env()` outside of config files.**

```php
// ❌ Wrong — calling env() directly in a service class
class StripeService
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = env('STRIPE_SECRET'); // breaks in production
    }
}

// ✓ Correct — read from config, which reads from env
class StripeService
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('cashier.secret'); // always works
    }
}
```

**Why does it matter?**

In production you run `php artisan config:cache` which serialises every config file into a single cached file. After caching, `env()` calls return `null` everywhere — the `.env` file is no longer read. Only `config()` reads from the cache. If your code calls `env()` directly, it breaks in production as soon as you cache the config.

Search the FreelanceFlow codebase for any direct `env()` calls outside of `config/` directories:

```bash
rg -n "env\(" app routes database
```

Fix every result by moving the value into a config file and reading it with `config()`.

---

## Step 2 — Create a FreelanceFlow Config File

Laravel ships with config files for cache, database, mail, queue, etc. Create one for FreelanceFlow-specific settings:

Create `config/freelanceflow.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Settings
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'FreelanceFlow'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        // Invoice number prefix: INV-2026-001
        'prefix' => env('INVOICE_PREFIX', 'INV'),

        // Default payment terms in days
        'default_due_days' => (int) env('INVOICE_DEFAULT_DUE_DAYS', 30),

        // Default GST/tax rate percentage
        'default_tax_rate' => (float) env('INVOICE_DEFAULT_TAX_RATE', 18.0),

        // Maximum file size for invoice PDF in KB
        'pdf_max_size_kb' => (int) env('INVOICE_PDF_MAX_SIZE_KB', 5120),

        // Currency code
        'currency' => env('INVOICE_CURRENCY', 'INR'),

        // Currency symbol
        'currency_symbol' => env('INVOICE_CURRENCY_SYMBOL', '₹'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        // Maximum file size for project attachments in KB (default 10 MB)
        'max_size_kb' => (int) env('UPLOAD_MAX_SIZE_KB', 10240),

        // Allowed MIME types for project attachments
        'allowed_mimes' => explode(',', env(
            'UPLOAD_ALLOWED_MIMES',
            'pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip'
        )),

        // Storage disk for attachments (local or s3)
        'disk' => env('UPLOAD_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Settings
    |--------------------------------------------------------------------------
    */

    'workspace' => [
        // Maximum clients per workspace on free plan
        'free_client_limit'   => (int) env('WORKSPACE_FREE_CLIENT_LIMIT', 10),

        // Maximum projects per workspace on free plan
        'free_project_limit'  => (int) env('WORKSPACE_FREE_PROJECT_LIMIT', 25),

        // Maximum team members on free plan
        'free_member_limit'   => (int) env('WORKSPACE_FREE_MEMBER_LIMIT', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        // Cache TTL in seconds for dashboard stats
        'cache_ttl' => (int) env('DASHBOARD_CACHE_TTL', 300),

        // Default revenue chart period in months
        'default_chart_months' => (int) env('DASHBOARD_DEFAULT_CHART_MONTHS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Support Settings
    |--------------------------------------------------------------------------
    */

    'support' => [
        'email' => env('SUPPORT_EMAIL', 'support@freelanceflow.test'),
        'url'   => env('SUPPORT_URL', 'https://freelanceflow.test/support'),
    ],

];
```

Now use it throughout the app:

```php
// Instead of hardcoded 18.0
$defaultTaxRate = config('freelanceflow.invoice.default_tax_rate');

// Instead of hardcoded 10240
$maxSize = config('freelanceflow.uploads.max_size_kb');

// Instead of hardcoded '₹'
$symbol = config('freelanceflow.invoice.currency_symbol');

// In the Invoice model
public static function generateNumber(): string
{
    $prefix = config('freelanceflow.invoice.prefix');
    $year   = now()->year;
    $count  = static::whereYear('created_at', $year)->count() + 1;
    return sprintf('%s-%d-%03d', $prefix, $year, $count);
}
```

---

## Step 3 — Update Validation Rules to Use Config

Update the file upload validation in the `Edit` Livewire project component to use config values:

```php
// app/Livewire/Projects/Edit.php
#[Rule([
    'nullable',
    'file',
    'max:' . \Illuminate\Support\Facades\Config::get('freelanceflow.uploads.max_size_kb', 10240),
])]
public $newFile = null;
```

Or more cleanly in the validation method itself:

```php
public function uploadFile(): void
{
    $maxSizeKb   = config('freelanceflow.uploads.max_size_kb', 10240);
    $allowedMimes = implode(',', config('freelanceflow.uploads.allowed_mimes', []));

    $this->validate([
        'newFile' => [
            'nullable',
            'file',
            "max:{$maxSizeKb}",
            "mimes:{$allowedMimes}",
        ],
    ]);

    if (! $this->newFile) {
        return;
    }

    $disk       = config('freelanceflow.uploads.disk', 'local');
    $storedName = $this->newFile->store('attachments', $disk);

    $this->project->attachments()->create([
        'original_name' => $this->newFile->getClientOriginalName(),
        'stored_name'   => $storedName,
        'mime_type'     => $this->newFile->getMimeType(),
        'size'          => $this->newFile->getSize(),
        'disk'          => $disk,
    ]);

    $this->newFile = null;
    $this->reset('newFile');
    $this->project->refresh();

    session()->flash('success', 'File uploaded successfully.');
}
```

---

## Step 4 — Environment Files

Laravel uses multiple `.env` files for different environments:

```
.env              ← local development (never committed)
.env.example      ← committed — template with all keys, no real values
.env.testing      ← used when running tests (PHPUnit reads this automatically)
.env.staging      ← manually loaded on staging server
```

Create `.env.testing` for clean test configuration:

```env
APP_NAME="FreelanceFlow Test"
APP_ENV=testing
APP_KEY=base64:your-test-key-here
APP_DEBUG=true
APP_URL=http://localhost

# Use SQLite in memory for fast tests
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Null drivers — no real side effects during tests
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
LOG_CHANNEL=null

# Stripe test mode
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_test_...

# Sentry disabled in tests
SENTRY_LARAVEL_DSN=null
```

With `DB_DATABASE=:memory:` and `DB_CONNECTION=sqlite`, tests use an in-memory SQLite database that is created fresh for each test and never touches your real MySQL database.

---

## Step 5 — Complete .env.example

Every variable FreelanceFlow uses must be documented in `.env.example`. This file is committed to Git and is the canonical reference:

```env
# =============================================================================
# FreelanceFlow — Environment Configuration
# Copy this file to .env and fill in your values
# =============================================================================

# Application
APP_NAME="FreelanceFlow"
APP_ENV=local
APP_KEY=                          # Run: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Kolkata

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freelance_flow
DB_USERNAME=root
DB_PASSWORD=

# Cache (use redis in production for performance)
CACHE_STORE=database             # local: database | production: redis
CACHE_PREFIX=freelanceflow_

# Session
SESSION_DRIVER=database          # local: database | production: redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Queue (use redis in production for performance)
QUEUE_CONNECTION=database        # local: database | production: redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1             # local: Mailpit | production: smtp.postmarkapp.com
MAIL_PORT=1025                  # local: 1025 (Mailpit) | production: 587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@freelanceflow.test"
MAIL_FROM_NAME="${APP_NAME}"

# Stripe Payments
STRIPE_KEY=pk_test_              # Get from dashboard.stripe.com
STRIPE_SECRET=sk_test_
STRIPE_WEBHOOK_SECRET=whsec_    # Get from: stripe listen --print-secret

# Sentry Error Tracking (leave blank for local dev)
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1

# Slack Logging (critical errors only)
LOG_SLACK_WEBHOOK_URL=          # Get from Slack app settings

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug                  # local: debug | production: warning

# FreelanceFlow Invoice Settings
INVOICE_PREFIX=INV
INVOICE_DEFAULT_DUE_DAYS=30
INVOICE_DEFAULT_TAX_RATE=18.0
INVOICE_CURRENCY=INR
INVOICE_CURRENCY_SYMBOL=₹

# FreelanceFlow Upload Settings
UPLOAD_MAX_SIZE_KB=10240        # 10 MB
UPLOAD_ALLOWED_MIMES=pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip
UPLOAD_DISK=local               # local: local | production: s3

# FreelanceFlow Workspace Limits (free plan)
WORKSPACE_FREE_CLIENT_LIMIT=10
WORKSPACE_FREE_PROJECT_LIMIT=25
WORKSPACE_FREE_MEMBER_LIMIT=1

# Dashboard
DASHBOARD_CACHE_TTL=300         # seconds
DASHBOARD_DEFAULT_CHART_MONTHS=12

# Support
SUPPORT_EMAIL=support@freelanceflow.test
SUPPORT_URL=https://freelanceflow.test/support

# AWS S3 (for production file storage)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Redis (required for production queue and cache)
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## Step 6 — Runtime Config Validation

Fail fast if required config values are missing. Add this to `app/Providers/AppServiceProvider.php`:

```php
public function boot(): void
{
    // In production, validate that all required config values are set
    // This catches missing environment variables before they cause runtime errors
    if (app()->isProduction()) {
        $this->validateRequiredConfig();
    }
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
```

This throws immediately on app boot in production if any critical variable is missing — far better than a cryptic runtime error deep in a payment flow.

---

## Step 7 — Config Caching in Production

```bash
# Cache all config files into a single optimised file
php artisan config:cache

# This creates: bootstrap/cache/config.php

# Clear the config cache (after changing .env or config files)
php artisan config:clear

# View the current config cache status
php artisan about | grep "Config"

# Full optimisation for production (run this in your CI/CD pipeline)
php artisan optimize
# Equivalent to:
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache
# php artisan event:cache

# Clear all caches
php artisan optimize:clear
```

**Important:** Never run `config:cache` in local development. The cache does not update automatically when you change `.env` — you would need to run `config:clear` every time you change an environment variable. Use it only in your CI/CD pipeline before deploying.

---

## Step 8 — Access Config in Blade

```blade
{{-- Use config() helper directly in Blade --}}
<p>{{ config('app.name') }}</p>
<p>{{ config('freelanceflow.support.email') }}</p>

{{-- Currency symbol from config --}}
<span>{{ config('freelanceflow.invoice.currency_symbol') }}{{ number_format($invoice->total, 2) }}</span>

{{-- Conditionally show features based on environment --}}
@if (config('app.debug'))
    <div class="debug-bar"><!-- debug info --></div>
@endif

@if (app()->isProduction())
    <!-- analytics scripts -->
@endif
```

---

## Step 9 — Typed Config Access Helper

For type safety and IDE autocompletion, create a typed config accessor:

```php
<?php

// app/Support/FreelanceFlowConfig.php
namespace App\Support;

class FreelanceFlowConfig
{
    public static function invoicePrefix(): string
    {
        return config('freelanceflow.invoice.prefix', 'INV');
    }

    public static function defaultDueDays(): int
    {
        return (int) config('freelanceflow.invoice.default_due_days', 30);
    }

    public static function defaultTaxRate(): float
    {
        return (float) config('freelanceflow.invoice.default_tax_rate', 18.0);
    }

    public static function currencySymbol(): string
    {
        return config('freelanceflow.invoice.currency_symbol', '₹');
    }

    public static function uploadMaxSizeKb(): int
    {
        return (int) config('freelanceflow.uploads.max_size_kb', 10240);
    }

    public static function allowedMimes(): array
    {
        return config('freelanceflow.uploads.allowed_mimes', []);
    }

    public static function uploadDisk(): string
    {
        return config('freelanceflow.uploads.disk', 'local');
    }

    public static function dashboardCacheTtl(): int
    {
        return (int) config('freelanceflow.dashboard.cache_ttl', 300);
    }

    public static function freeClientLimit(): int
    {
        return (int) config('freelanceflow.workspace.free_client_limit', 10);
    }
}
```

Use it anywhere:

```php
use App\Support\FreelanceFlowConfig;

// Typed, IDE-friendly, no string typos
$symbol  = FreelanceFlowConfig::currencySymbol();
$maxSize = FreelanceFlowConfig::uploadMaxSizeKb();
$dueDays = FreelanceFlowConfig::defaultDueDays();
```

---

## What We Learned Today

- **Never call `env()` outside config files** — after `php artisan config:cache`, `env()` returns `null` everywhere except config files. Use `config()` to read values in application code
- **Custom config files** — `config/freelanceflow.php` centralises all app-specific settings. Cast types immediately (`(int)`, `(float)`) because env values are always strings
- **`.env.example`** — the canonical reference committed to Git. Every variable documented, no real values. New developers copy it to `.env` and fill in their own
- **`.env.testing`** — read automatically by PHPUnit. Use `DB_DATABASE=:memory:` for blazing-fast in-memory SQLite tests. `QUEUE_CONNECTION=sync` runs jobs immediately instead of queuing
- **Runtime config validation in production** — check required variables on boot in production. Fail immediately with a clear message rather than a cryptic runtime error later
- **`php artisan optimize`** — caches config, routes, views, and events for production. Run in CI/CD before deployment
- **Typed config accessor** — `FreelanceFlowConfig` class wraps `config()` calls with return type declarations, giving IDE autocompletion and eliminating string key typos
- **`explode(',', env('UPLOAD_ALLOWED_MIMES', ''))` pattern** — store arrays as comma-separated env values, explode in the config file

---

## Day 37 — Phase 2 Review & Refactor

Tomorrow we close Phase 2. We will do a full codebase audit — checking for N+1 queries that crept in, ensuring every route is protected, verifying all models have complete `$fillable` arrays, running `migrate:fresh --seed` to confirm the full database setup works from scratch, and pushing FreelanceFlow v1.0 to GitHub with a complete Phase 2 changelog. From Day 38 we enter Phase 3 — Redis caching, real-time with Reverb, and comprehensive testing.

See you on Day 37.
