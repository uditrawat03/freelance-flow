# Day 37 — Phase 2 Review & Refactor

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"Twenty-two days of building. Before we cross into Phase 3, we stop. We audit every part of FreelanceFlow — routes, models, queries, config, seeders — with the same discipline we would apply before a production launch. This is not cleanup for its own sake. Phase 3 builds on Phase 2. Every bug we fix now is a bug that does not derail a Day 45 post."*

---

## Phase 2 — What We Built

| Day | Feature |
|---|---|
| 16 | Eloquent relationships — clients have projects |
| 17 | Many-to-many — project tags, pivot tables |
| 18 | Eager loading — N+1 fixed, Debugbar installed |
| 19 | File uploads — project attachments, secure download |
| 20 | Sending emails — Mailable classes, Mailpit |
| 21 | Queues and jobs — background email, retry logic |
| 22 | Events and listeners — ProjectCreated event, decoupled dispatch |
| 23 | Notifications — database bell, email, InvoiceOverdue |
| 24 | REST API with Sanctum — token auth, versioned routes |
| 25 | API polish — ForceJson, rate limiting, error responses |
| 26 | Invoice generation — PDF with DomPDF, InvoiceService |
| 27 | Stripe payments — PaymentIntent, webhooks, Stripe CLI |
| 28 | Dashboard with charts — Chart.js, cached aggregates |
| 29 | Policies and Gates — OwnedByUser scope, authorization |
| 30 | Roles and permissions — Spatie, invoice UI completion |
| 31 | Multi-tenancy — workspaces, BelongsToWorkspace scope |
| 32 | Service classes — ClientService, ProjectService, DashboardService |
| 33 | Artisan commands — scheduled tasks, overdue checks |
| 34 | Repository pattern — interfaces, Eloquent implementations, fakes |
| 35 | Logging and Sentry — structured logs, error tracking |
| 36 | Config management — freelanceflow.php, env vs config, .env.example |

---

## The Audit Checklist

Work through each section methodically. Fix issues as you find them.

---

### 1. Debug Code Sweep

```bash
# Find any leftover debug calls
rg -n "dd\(|dump\(|var_dump\(|ray\(|ddd\(" app
rg -n "console\.log" resources/js resources/views
```

Each search should return nothing. If any match is found — remove it before continuing.

---

### 2. env() Outside Config Sweep

```bash
# Find direct env() calls outside the config directory
rg -n "env\(" app routes database
```

Every result is a potential production bug when `config:cache` runs. Move each value into `config/freelanceflow.php` or the appropriate existing config file and replace the `env()` call with `config()`.

---

### 3. Route Audit

Open `routes/web.php` and verify every route that handles data is inside the `auth` middleware group:

```php
// routes/web.php — complete verified structure

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;
use App\Livewire\Clients\ClientList;
use App\Livewire\Dashboard;
use App\Livewire\Invoices\Create as CreateInvoice;
use App\Livewire\Invoices\InvoiceList;
use App\Livewire\Projects\Create as CreateProject;
use App\Livewire\Projects\Edit as EditProject;
use App\Livewire\Workspaces\Create as CreateWorkspace;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------
// Guest-only routes (redirect to dashboard if logged in)
// -------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login',    Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->middleware('auth')->name('logout');

// -------------------------------------------------------
// Stripe webhook — public, CSRF excluded
// -------------------------------------------------------
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
     ->name('stripe.webhook');

// -------------------------------------------------------
// Payment pages — public (clients pay without logging in)
// -------------------------------------------------------
Route::get('/invoices/{invoice}/pay',
    [PaymentController::class, 'show'])->name('invoices.pay');
Route::get('/invoices/{invoice}/pay/success',
    [PaymentController::class, 'success'])->name('invoices.pay.success');

// -------------------------------------------------------
// Authenticated routes
// -------------------------------------------------------
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/', fn () => redirect()->route('dashboard'));

    // Workspace
    Route::get('/workspaces/create', CreateWorkspace::class)->name('workspaces.create');

    // Clients
    Route::get('/clients',                ClientList::class)->name('clients.index');
    Route::get('/clients/create',         CreateClient::class)->name('clients.create');
    Route::get('/clients/{client}',       [\App\Http\Controllers\ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit',  EditClient::class)->name('clients.edit');

    // Projects
    Route::get('/projects/create',           CreateProject::class)->name('projects.create');
    Route::get('/projects/{project}/edit',   EditProject::class)->name('projects.edit');
    Route::get('/projects/{project}',        [ProjectController::class, 'show'])->name('projects.show');

    // Invoices
    Route::get('/invoices',                  InvoiceList::class)->name('invoices.index');
    Route::get('/invoices/create',           CreateInvoice::class)->name('invoices.create');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::get('/invoices/{invoice}/preview',  [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::post('/invoices/{invoice}/send',    [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/paid',    [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');

    // Attachments
    Route::get('/attachments/{attachment}/download',
        [AttachmentController::class, 'download'])->name('attachments.download');

});
```

Run `php artisan route:list` and verify:
- Every `/clients/*`, `/projects/*`, `/invoices/*` route shows the `auth` middleware
- The webhook route has NO `auth` or `csrf` middleware
- The payment pages have NO `auth` middleware

---

### 4. Model $fillable Audit

Every model must have a complete `$fillable` array. Missing columns cause `MassAssignmentException` silently returning null on save.

```php
// Client
protected $fillable = [
    'workspace_id', 'user_id',
    'name', 'email', 'phone', 'company', 'notes', 'status',
];

// Project
protected $fillable = [
    'workspace_id', 'user_id', 'client_id',
    'name', 'description', 'status', 'budget', 'deadline',
];

// Invoice
protected $fillable = [
    'workspace_id', 'user_id', 'client_id', 'project_id',
    'number', 'status', 'notes',
    'line_items', 'subtotal', 'tax_rate', 'tax_amount', 'total',
    'issued_at', 'due_at', 'paid_at', 'pdf_path',
    'stripe_payment_intent_id', 'stripe_payment_status',
];

// Attachment
protected $fillable = [
    'project_id', 'original_name', 'stored_name',
    'mime_type', 'size', 'disk',
];

// Tag
protected $fillable = ['name', 'slug', 'colour'];

// Workspace
protected $fillable = ['name', 'slug', 'owner_id', 'plan', 'settings'];
```

---

### 5. Global Scope Verification

All three main models must have the `BelongsToWorkspace` global scope and the `creating` hook. Open each model and verify:

```php
// In Client, Project, and Invoice models
use App\Models\Scopes\BelongsToWorkspace;

protected static function booted(): void
{
    static::addGlobalScope(new BelongsToWorkspace);

    static::creating(function (self $model) {
        if (auth()->check() && ! $model->workspace_id) {
            $model->workspace_id = auth()->user()->currentWorkspace()?->id;
        }
    });
}
```

Test in Tinker:

```bash
php artisan tinker

# Log in as the demo user (simulate auth)
$user = App\Models\User::where('email', 'demo@freelanceflow.test')->first();
auth()->login($user);
session(['current_workspace_id' => $user->workspaces->first()->id]);

# Verify global scope is working
App\Models\Client::toRawSql(); // should include WHERE workspace_id = ?

# Verify scope is correctly limiting results
App\Models\Client::count(); // should return only clients for this workspace
```

---

### 6. N+1 Check on Key Pages

With Debugbar installed (`composer require barryvdh/laravel-debugbar --dev`), visit each of these pages and check the query count in the Queries tab:

| Page | Expected max queries | Common N+1 culprit |
|---|---|---|
| `/clients` | 3–4 | Tags on each client |
| `/clients/{id}` | 3 | Projects + tags per project |
| `/dashboard` | 5–8 | Recent activity list |
| `/invoices` | 2–3 | Client name per invoice |
| `/projects/{id}` | 3 | Client, tags, attachments |

Fix any page exceeding the expected count by adding `with()` or `withCount()` to the repository method that powers it.

---

### 7. Fresh Seed Verification

```bash
# Full reset and reseed
php artisan migrate:fresh --seed

# Should output:
# ✓ Roles and permissions seeded
# ✓ Seeded 50 clients and ~90 projects
# ✓ Seeded 15 tags and attached to all projects

# Verify counts
php artisan tinker
>>> App\Models\Client::withoutGlobalScopes()->count()   // 50
>>> App\Models\Project::withoutGlobalScopes()->count()  // ~90
>>> App\Models\Tag::count()                             // 15
>>> App\Models\User::count()                            // 1
>>> App\Models\Workspace::count()                       // 1
```

If `migrate:fresh --seed` throws any error — fix it. This is the first command a new developer will run.

---

### 8. Queue and Scheduler Check

```bash
# Verify all jobs can be dispatched without errors
php artisan tinker
>>> App\Jobs\SendProjectNotification::dispatchSync(App\Models\Project::first());

# Verify all scheduled commands are registered
php artisan schedule:list

# Should show:
# App\Console\Commands\CheckOverdueInvoices     Daily at 07:00
# App\Console\Commands\SendInvoiceReminders     Daily at 09:00
# App\Console\Commands\GenerateMonthlyRevenueReport  Monthly on 1st at 08:00
# App\Console\Commands\ArchiveStaleLeads        Weekly on Sundays at 00:00
# livewire:clean-uploads                        Daily
# queue:prune-failed                            Daily

# Run a dry-run of the overdue check
php artisan invoice:check-overdue --dry-run
```

---

### 9. API Smoke Test

```bash
# Start the server
php artisan serve

# Get a token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/tokens/create \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@freelanceflow.test","password":"password","device_name":"audit"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

# Test each endpoint
curl -s http://localhost:8000/api/v1/clients \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool | head -20

curl -s http://localhost:8000/api/v1/projects \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool | head -20

curl -s http://localhost:8000/api/v1/tags \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# Test unauthenticated returns 401 JSON (not HTML)
curl -s http://localhost:8000/api/v1/clients | python3 -m json.tool

# Test rate limiting header present
curl -I http://localhost:8000/api/v1/clients \
  -H "Authorization: Bearer $TOKEN" | grep -i "x-ratelimit"
```

---

### 10. Default Test Suite

```bash
php artisan test

# All default Laravel tests should pass:
# PASS  Tests\Unit\ExampleTest
# PASS  Tests\Feature\ExampleTest
# Tests: 2 passed
```

---

## Write the Phase 2 Changelog

Add a `CHANGELOG.md` to the project root:

```markdown
# Changelog

## [1.0.0] — Phase 2 Complete — 2026-05-xx

### Added
- Eloquent relationships: clients → projects (hasMany/belongsTo)
- Many-to-many: project tags with pivot table
- Eager loading: N+1 eliminated with with() and withCount()
- File uploads: project attachments stored on private local disk
- Email system: Mailable classes, Mailpit for local dev
- Queue system: database driver, background jobs, retry logic
- Events and listeners: ProjectCreated, decoupled side effects
- Notifications: database bell icon, InvoiceOverdue email
- REST API v1: Sanctum token auth, versioned routes, JSON Resources
- API polish: ForceJsonResponse, rate limiting, global exception handling
- Invoice generation: PDF with DomPDF, InvoiceService, download/preview
- Stripe payments: PaymentIntent, Stripe Elements, webhook handler
- Dashboard: Chart.js bar and doughnut charts, cached aggregates
- Authorization: Policies, Gates, OwnedByUser/BelongsToWorkspace scopes
- Roles and permissions: Spatie Laravel Permission, admin/manager/freelancer
- Multi-tenancy: workspaces, workspace switcher, BelongsToWorkspace scope
- Service classes: ClientService, ProjectService, InvoiceService, DashboardService
- Repository pattern: interfaces, Eloquent implementations, FakeClientRepository
- Artisan commands: CheckOverdueInvoices, SendInvoiceReminders, MonthlyRevenueReport
- Task scheduling: all commands registered in routes/console.php
- Logging: structured logs, Sentry integration, Slack critical alerts
- Config management: config/freelanceflow.php, complete .env.example

### Changed
- Middleware pattern: constructor middleware removed, all middleware on routes
- Invoice UI: complete Create/List Livewire components (was incomplete after Day 26)
- DashboardService: now uses repository interfaces instead of direct Eloquent calls
- All services: updated to inject repositories instead of calling Eloquent directly

### Fixed
- N+1 queries on client show page (projects + tags)
- Missing $fillable entries on Attachment and Invoice models
- env() calls in application code (moved to config files)
```

---

## Push FreelanceFlow v1.0

```bash
# Stage everything
git add .

# Commit
git commit -m "feat: FreelanceFlow v1.0 — Phase 2 complete

Phase 2 adds 22 features across invoicing, payments, APIs,
authorization, multi-tenancy, services, repositories, and
scheduled tasks. Full changelog in CHANGELOG.md."

# Tag the release
git tag -a v1.0.0 -m "FreelanceFlow v1.0.0 — Phase 2 complete"

# Push with tags
git push origin main --tags
```

---

## Phase 3 Preview — What Is Coming

From Day 38 we enter Phase 3. The complexity increases significantly — Redis caching, real-time WebSockets, comprehensive testing, full-text search, two-factor authentication, and advanced Livewire patterns.

| Days | Topic |
|---|---|
| 38–39 | Redis caching — cache driver, tags, TTL strategies |
| 40 | Cache strategies — cache-aside, write-through, event-driven busting |
| 41–42 | Real-time with Reverb — WebSocket channels, broadcasting events |
| 43–44 | Private and presence channels — auth channels, team presence |
| 45–46 | Feature testing — PHPUnit, RefreshDatabase, HTTP tests |
| 47–48 | HTTP tests and mocking — actingAs, Mail::fake(), Queue::fake() |
| 49 | Browser testing with Dusk |
| 50-51 | Advanced Livewire and Inertia analytics |
| 52 | Rate limiting deep dive |
| 53 | Encryption and hashing |
| 54 | Two-factor authentication |
| 55–56 | Horizon and queue monitoring |
| 57 | Telescope for debugging |
| 58 | Localization and i18n |
| 59–60 | Livewire advanced patterns |
| 61 | Inertia.js introduction |
| 62 | GraphQL with Lighthouse |
| 53 | Octane with FrankenPHP |
| 64 | Phase 3 review |

The patterns you learned in Phase 2 — services, repositories, events, policies, queues — all appear again in Phase 3. The new concepts build on the foundation rather than replacing it.

---

## What We Accomplished in Phase 2

Read this list slowly. In 22 days, starting from a basic client list, you built:

- A complete invoicing system with PDF generation and Stripe payment processing
- A REST API with token authentication, rate limiting, and JSON Resources
- A multi-workspace multi-tenant architecture with per-workspace data isolation
- A role-based authorization system with policies, gates, and Spatie permissions
- A service and repository layer that makes every piece of business logic testable in isolation
- Automated background tasks with retry logic, failure handling, and Slack alerts
- Structured logging with context, Sentry error tracking, and environment-aware configuration

Every one of these is production-quality work. FreelanceFlow v1.0 is not a tutorial app. It is the foundation of a real SaaS product.
