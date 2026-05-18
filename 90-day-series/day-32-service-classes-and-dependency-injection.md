# Day 32 — Service Classes & Dependency Injection

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"Livewire components in FreelanceFlow are getting heavy. The Create Invoice component calculates totals, generates invoice numbers, creates the model, and calls the InvoiceService. The Dashboard component queries five different tables and formats chart data. That logic does not belong in UI components. Today we extract it into Service classes — thin, focused, injectable, testable."*

---

## The Problem — Fat Components

Look at what a Livewire component should do: handle user input, validate it, call a service, return a response. That is it. When a component also contains business logic — calculating totals, formatting data, making API calls, transforming models — it becomes impossible to test that logic in isolation and difficult to reuse it elsewhere.

The Service class pattern solves this. Each service owns one slice of business logic. Controllers, Livewire components, artisan commands, and API endpoints all call the same service — the logic never duplicates.

---

## What We Are Building Today

1. **`ClientService`** — client creation, search, and statistics
2. **`ProjectService`** — project creation, status transitions, overdue detection
3. **`DashboardService`** — all dashboard aggregates in one injectable class
4. **Refactor Livewire components** to call services instead of doing their own queries
5. **Service container binding** — singletons and scoped bindings
6. **Method injection** — inject services into Livewire action methods directly

---

## Step 1 — ClientService

```bash
mkdir -p app/Services
```

Create `app/Services/ClientService.php`:

```php
<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ClientService
{
    /**
     * Paginated, filtered client list for the current workspace.
     */
    public function list(
        string $search  = '',
        string $status  = '',
        int    $perPage = 15,
    ): LengthAwarePaginator {
        return Client::query()
            ->withCount('projects')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('company', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q) => $q->status($status))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a new client and assign to current workspace.
     */
    public function create(array $data): Client
    {
        return Client::create($data);
        // workspace_id and user_id are auto-assigned via model booted() hooks
    }

    /**
     * Update an existing client.
     */
    public function update(Client $client, array $data): Client
    {
        $client->update($data);
        return $client->fresh();
    }

    /**
     * Soft delete a client and their projects.
     */
    public function delete(Client $client): void
    {
        // Soft-delete all projects before deleting the client
        $client->projects()->each(fn ($project) => $project->delete());
        $client->delete();
    }

    /**
     * Summary statistics for the current workspace.
     * Cached for 5 minutes per workspace.
     */
    public function statistics(): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("client_stats_{$workspaceId}", 300, function () {
            return [
                'total'    => Client::count(),
                'active'   => Client::active()->count(),
                'inactive' => Client::inactive()->count(),
                'leads'    => Client::leads()->count(),
            ];
        });
    }

    /**
     * Bust the client statistics cache.
     * Call whenever a client is created, updated, or deleted.
     */
    public function bustCache(): void
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;
        Cache::forget("client_stats_{$workspaceId}");
    }
}
```

---

## Step 2 — ProjectService

Create `app/Services/ProjectService.php`:

```php
<?php

namespace App\Services;

use App\Events\ProjectCreated;
use App\Models\Project;
use Illuminate\Support\Collection;

class ProjectService
{
    /**
     * Create a project and fire the ProjectCreated event.
     */
    public function create(array $data, array $tagIds = []): Project
    {
        $project = Project::create($data);

        if (! empty($tagIds)) {
            $project->tags()->sync($tagIds);
        }

        // Event fires listeners: SendProjectNotification, LogProjectActivity
        ProjectCreated::dispatch($project);

        return $project;
    }

    /**
     * Update project and sync tags.
     */
    public function update(Project $project, array $data, array $tagIds = []): Project
    {
        $previousStatus = $project->status;

        $project->update($data);
        $project->tags()->sync($tagIds);

        // Fire status change notification if status changed
        if ($previousStatus !== $project->status) {
            $project->loadMissing('client');
            auth()->user()->notify(
                new \App\Notifications\ProjectStatusChanged($project, $previousStatus)
            );
        }

        return $project->fresh(['client', 'tags']);
    }

    /**
     * Soft delete a project and remove file attachments.
     */
    public function delete(Project $project): void
    {
        // Delete stored files before soft-deleting
        $project->attachments->each(function ($attachment) {
            $attachment->deleteFromStorage();
            $attachment->delete();
        });

        $project->delete();
    }

    /**
     * Projects that are overdue for the current workspace.
     */
    public function overdueProjects(): Collection
    {
        return Project::overdue()
            ->with('client')
            ->latest('deadline')
            ->get();
    }

    /**
     * Status distribution for the current workspace.
     */
    public function statusBreakdown(): array
    {
        return Project::query()
            ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
```

---

## Step 3 — DashboardService

Create `app/Services/DashboardService.php`:

```php
<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private readonly ClientService  $clientService,
        private readonly ProjectService $projectService,
    ) {}

    /**
     * All key metrics for the dashboard stats grid.
     */
    public function stats(): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("dashboard_stats_{$workspaceId}", 300, function () {
            return [
                'total_clients'         => Client::active()->count(),
                'active_projects'       => Project::active()->count(),
                'unpaid_invoices'       => Invoice::unpaid()->count(),
                'overdue_invoices'      => Invoice::overdue()->count(),
                'total_revenue'         => Invoice::paid()->sum('total'),
                'revenue_this_month'    => Invoice::paid()
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('total'),
            ];
        });
    }

    /**
     * Revenue chart data for the given number of months.
     */
    public function revenueChart(int $months = 12): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("revenue_chart_{$months}_{$workspaceId}", 300, function () use ($months) {
            $labels = [];
            $data   = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $date     = now()->subMonths($i);
                $labels[] = $date->format('M Y');
                $data[]   = (float) Invoice::paid()
                    ->whereMonth('paid_at', $date->month)
                    ->whereYear('paid_at', $date->year)
                    ->sum('total');
            }

            return ['labels' => $labels, 'data' => $data, 'total' => array_sum($data)];
        });
    }

    /**
     * Project status breakdown for the doughnut chart.
     */
    public function projectStatusBreakdown(): array
    {
        return $this->projectService->statusBreakdown();
    }

    /**
     * Recent activity for the dashboard feed.
     */
    public function recentActivity(): array
    {
        return [
            'clients'  => Client::latest()->limit(5)->get(),
            'projects' => Project::with('client')->latest()->limit(5)->get(),
            'invoices' => Invoice::with('client')->latest()->limit(5)->get(),
        ];
    }

    /**
     * Overdue items that need attention.
     */
    public function overdueItems(): array
    {
        return [
            'invoices' => Invoice::overdue()->with('client')->limit(5)->get(),
            'projects' => $this->projectService->overdueProjects()->take(5),
        ];
    }

    /**
     * Bust all dashboard caches for the current workspace.
     */
    public function bustCache(): void
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        foreach (['dashboard_stats', 'revenue_chart_3', 'revenue_chart_6', 'revenue_chart_12'] as $key) {
            Cache::forget("{$key}_{$workspaceId}");
        }

        $this->clientService->bustCache();
    }
}
```

---

## Step 4 — Bind Services in the Service Container

Open `app/Providers/AppServiceProvider.php`:

```php
use App\Services\ClientService;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Services\ProjectService;

public function register(): void
{
    // Singleton — same instance for the entire request lifecycle
    // Good for services that cache data or maintain state within a request
    $this->app->singleton(ClientService::class);
    $this->app->singleton(ProjectService::class);
    $this->app->singleton(InvoiceService::class);
    $this->app->singleton(DashboardService::class);
}
```

With `singleton` bindings, the service container creates one instance per request and reuses it. If `ClientService` is injected into three different Livewire components during one request, they all share the same instance — including any cached data it holds in memory.

---

## Step 5 — Refactor the Dashboard Livewire Component

Compare before and after:

```php
// Before — Dashboard component doing its own querying (Day 28)
public function render()
{
    return view('livewire.dashboard', [
        'stats'             => $this->getStats(),     // ← business logic in component
        'revenueChart'      => $this->getRevenueChartData(),
        'projectStatusData' => $this->getProjectStatusData(),
        'recent'            => $this->getRecentActivity(),
        'overdue'           => $this->getOverdueItems(),
    ]);
}

// After — Dashboard component delegates to DashboardService
public function render(DashboardService $dashboardService)
{
    $months = match($this->period) {
        '3months'  => 3,
        '6months'  => 6,
        default    => 12,
    };

    return view('livewire.dashboard', [
        'stats'             => $dashboardService->stats(),
        'revenueChart'      => $dashboardService->revenueChart($months),
        'projectStatusData' => $dashboardService->projectStatusBreakdown(),
        'recent'            => $dashboardService->recentActivity(),
        'overdue'           => $dashboardService->overdueItems(),
    ]);
}
```

The component no longer has any private methods. It has one job: receive input, call the service, pass data to the view.

---

## Step 6 — Method Injection in Livewire Actions

Livewire supports method injection — services can be injected directly into action methods without adding them to the constructor. This is clean for services used in only one action:

```php
// app/Livewire/Clients/Create.php

// Constructor injection — use when the service is needed in multiple methods
public function __construct(
    private readonly ClientService $clientService,
) {}

// Method injection — use when the service is needed in just one action
public function save(ClientService $clientService): void
{
    $this->validate();

    $client = $clientService->create([
        'name'    => $this->name,
        'email'   => $this->email,
        'phone'   => $this->phone,
        'company' => $this->company,
        'notes'   => $this->notes,
        'status'  => $this->status,
    ]);

    $clientService->bustCache();

    session()->flash('success', 'Client added successfully.');
    $this->redirect(route('clients.index'), navigate: true);
}
```

---

## Step 7 — Refactor the Invoice Create Component

```php
// app/Livewire/Invoices/Create.php
use App\Services\InvoiceService;

public function save(InvoiceService $invoiceService): void
{
    $this->validate();

    // Validate line items
    foreach ($this->lineItems as $index => $item) {
        if (empty($item['description'])) {
            $this->addError("lineItems.{$index}.description", 'Description is required.');
            return;
        }
    }

    // Delegate entirely to the service
    $invoice = $invoiceService->create([
        'client_id'  => $this->client_id,
        'project_id' => $this->project_id ?: null,
        'notes'      => $this->notes,
        'tax_rate'   => $this->tax_rate,
        'issued_at'  => $this->issued_at ?: now()->toDateString(),
        'due_at'     => $this->due_at,
        'line_items' => $this->lineItems,
        'status'     => 'draft',
    ]);

    session()->flash('success', "Invoice {$invoice->number} created.");
    $this->redirect(route('invoices.index'), navigate: true);
}
```

The component had 30 lines of logic in `save()` on Day 30. It now has 15 — and those 15 lines are pure UI orchestration.

---

## Service vs Repository vs Action

Three patterns that beginners often confuse:

| Pattern | Purpose | When to use |
|---|---|---|
| **Service** | Business logic that spans multiple models or operations | Invoice creation, dashboard aggregates, email + DB in one call |
| **Repository** | Database access layer, abstracts Eloquent | When you need to swap data sources or mock DB in tests |
| **Action** | Single, focused operation (no state) | Simple one-off tasks like `SendWelcomeEmail` or `GeneratePdf` |

FreelanceFlow uses Services. Repositories add complexity without much benefit when you are using Eloquent and not planning to swap databases. Actions are useful but are essentially single-method services — the distinction rarely matters in practice.

---

## Testing a Service in Isolation

The payoff of extracting logic into services is testability. A service method can be tested without a browser, without HTTP, without a Livewire component:

```php
// tests/Unit/ClientServiceTest.php
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ClientService;

it('creates a client with correct workspace assignment', function () {
    $user      = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);
    session(['current_workspace_id' => $workspace->id]);

    $service = app(ClientService::class);

    $client = $service->create([
        'name'   => 'Test Client',
        'email'  => 'test@example.com',
        'status' => 'active',
    ]);

    expect($client->workspace_id)->toBe($workspace->id);
    expect($client->name)->toBe('Test Client');
    expect(Client::count())->toBe(1);
});
```

This test does not touch a browser, a controller, or a Livewire component. It tests exactly one thing: does `ClientService::create()` assign the correct workspace? Fast, focused, reliable.

---

## What We Learned Today

- **The service class pattern** — one class owns one slice of business logic. Controllers, Livewire components, API endpoints, and artisan commands all call the same service
- **Constructor injection** — declare services as `readonly` constructor parameters. Laravel's service container resolves them automatically
- **Method injection in Livewire** — services can be type-hinted in action method signatures. Laravel injects them without adding to the constructor
- **`singleton` binding** — `$this->app->singleton(Service::class)` creates one instance per request. Good for services that cache data within a request
- **`DashboardService` composing other services** — a service can depend on other services via its own constructor. Laravel resolves the full dependency tree automatically
- **`bustCache()` as a service method** — cache invalidation belongs in the service that owns the data. The Livewire component calls `bustCache()` after mutating data; it does not need to know which cache keys exist
- **Testability** — the primary reason for service classes. A service method can be called in a unit test without HTTP, without a browser, without Livewire. Test the logic, not the transport

---

## Day 33 — Artisan Commands & Task Scheduling

Tomorrow we automate FreelanceFlow. We will write custom Artisan commands — a daily overdue invoice check, a monthly revenue report generator — and schedule them with Laravel's task scheduler. By the end of Day 33 FreelanceFlow runs maintenance tasks automatically every day without any manual intervention.

See you on Day 33.