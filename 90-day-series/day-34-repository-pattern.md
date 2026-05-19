# Day 34 — Repository Pattern

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 14 min · **Level:** Intermediate

---

> *"Service classes in FreelanceFlow call Eloquent directly — Client::query(), Invoice::paid()->sum(). That works, but it means the service class is coupled to Eloquent. If you want to test ClientService without hitting the database, you cannot — the Eloquent calls are baked in. The Repository pattern adds a thin interface between the service and the data source, making every query swappable and every service testable in complete isolation."*

---

## What Is the Repository Pattern?

A Repository is a class that abstracts data access behind an interface. Instead of `ClientService` calling `Client::query()` directly, it calls `ClientRepository::paginate()`. The repository owns the Eloquent queries. The service owns the business logic. Neither knows about the other's implementation details.

```
Request → Controller/Livewire → Service → Repository → Eloquent → Database
```

The benefit is not abstraction for its own sake. The benefit is that in tests, you can swap the real repository for a fake one — and the service never knows the difference.

---

## What We Are Building Today

1. A **`ClientRepositoryInterface`** — the contract
2. An **`EloquentClientRepository`** — the Eloquent implementation
3. An **`InvoiceRepositoryInterface`** and implementation
4. **Bind the interfaces** to implementations in the service container
5. **Update `ClientService`** to inject the repository
6. A **fake repository** for testing

---

## Step 1 — Directory Structure

```bash
mkdir -p app/Repositories/Contracts
mkdir -p app/Repositories/Eloquent
```

---

## Step 2 — ClientRepositoryInterface

Create `app/Repositories/Contracts/ClientRepositoryInterface.php`:

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClientRepositoryInterface
{
    public function paginate(
        string $search  = '',
        string $status  = '',
        int    $perPage = 15,
    ): LengthAwarePaginator;

    public function find(int $id): ?Client;

    public function findOrFail(int $id): Client;

    public function create(array $data): Client;

    public function update(Client $client, array $data): Client;

    public function delete(Client $client): void;

    public function countByStatus(): array;

    public function activeClients(): Collection;
}
```

The interface defines the contract — what the repository can do. It says nothing about how. That is the implementation's job.

---

## Step 3 — EloquentClientRepository

Create `app/Repositories/Eloquent/EloquentClientRepository.php`:

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentClientRepository implements ClientRepositoryInterface
{
    public function paginate(
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

    public function find(int $id): ?Client
    {
        return Client::find($id);
    }

    public function findOrFail(int $id): Client
    {
        return Client::findOrFail($id);
    }

    public function create(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);
        return $client->fresh();
    }

    public function delete(Client $client): void
    {
        $client->projects()->each(fn ($p) => $p->delete());
        $client->delete();
    }

    public function countByStatus(): array
    {
        return Client::query()
            ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function activeClients(): Collection
    {
        return Client::active()->orderBy('name')->get();
    }
}
```

---

## Step 4 — InvoiceRepositoryInterface

Create `app/Repositories/Contracts/InvoiceRepositoryInterface.php`:

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    public function paginate(string $status = '', int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Invoice;

    public function findOrFail(int $id): Invoice;

    public function create(array $data): Invoice;

    public function update(Invoice $invoice, array $data): Invoice;

    public function delete(Invoice $invoice): void;

    public function totalRevenue(): float;

    public function revenueByMonth(int $months = 12): array;

    public function overdueInvoices(): \Illuminate\Support\Collection;
}
```

Create `app/Repositories/Eloquent/EloquentInvoiceRepository.php`:

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function paginate(string $status = '', int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('client')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): ?Invoice
    {
        return Invoice::find($id);
    }

    public function findOrFail(int $id): Invoice
    {
        return Invoice::findOrFail($id);
    }

    public function create(array $data): Invoice
    {
        $data['number'] = Invoice::generateNumber();
        $invoice = Invoice::create($data);
        $invoice->recalculate();
        return $invoice;
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);
        $invoice->recalculate();
        return $invoice->fresh();
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    public function totalRevenue(): float
    {
        return (float) Invoice::paid()->sum('total');
    }

    public function revenueByMonth(int $months = 12): array
    {
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

        return ['labels' => $labels, 'data' => $data];
    }

    public function overdueInvoices(): Collection
    {
        return Invoice::overdue()->with('client')->latest('due_at')->get();
    }
}
```

---

## Step 5 — Bind Interfaces in the Service Container

Open `app/Providers/AppServiceProvider.php`:

```php
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Eloquent\EloquentClientRepository;
use App\Repositories\Eloquent\EloquentInvoiceRepository;

public function register(): void
{
    // Bind interface to Eloquent implementation
    $this->app->bind(ClientRepositoryInterface::class, EloquentClientRepository::class);
    $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);

    // Services — singletons as before
    $this->app->singleton(\App\Services\ClientService::class);
    $this->app->singleton(\App\Services\ProjectService::class);
    $this->app->singleton(\App\Services\InvoiceService::class);
    $this->app->singleton(\App\Services\DashboardService::class);
}
```

Now whenever any class type-hints `ClientRepositoryInterface`, Laravel injects `EloquentClientRepository`. Change the implementation — update one line in `AppServiceProvider`. Nothing else changes.

---

## Step 6 — Update ClientService to Use the Repository

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

    public function list(
        string $search  = '',
        string $status  = '',
        int    $perPage = 15,
    ): LengthAwarePaginator {
        return $this->clients->paginate($search, $status, $perPage);
    }

    public function create(array $data): Client
    {
        $client = $this->clients->create($data);
        $this->bustCache();
        return $client;
    }

    public function update(Client $client, array $data): Client
    {
        $updated = $this->clients->update($client, $data);
        $this->bustCache();
        return $updated;
    }

    public function delete(Client $client): void
    {
        $this->clients->delete($client);
        $this->bustCache();
    }

    public function statistics(): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("client_stats_{$workspaceId}", 300, function () {
            return $this->clients->countByStatus();
        });
    }

    public function bustCache(): void
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;
        Cache::forget("client_stats_{$workspaceId}");
    }
}
```

The service no longer has a single Eloquent call. It only knows about `ClientRepositoryInterface`. Swap the implementation for a fake — the service works identically.

---

## Step 7 — Fake Repository for Testing

Create `app/Repositories/Fakes/FakeClientRepository.php`:

```php
<?php

namespace App\Repositories\Fakes;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class FakeClientRepository implements ClientRepositoryInterface
{
    private Collection $clients;

    public function __construct()
    {
        $this->clients = collect();
    }

    public function paginate(string $search = '', string $status = '', int $perPage = 15): LengthAwarePaginator
    {
        $filtered = $this->clients
            ->when($search, fn ($c) => $c->filter(
                fn ($client) => str_contains(strtolower($client->name), strtolower($search))
            ))
            ->when($status, fn ($c) => $c->where('status', $status))
            ->values();

        return new Paginator($filtered->all(), $filtered->count(), $perPage, 1);
    }

    public function find(int $id): ?Client
    {
        return $this->clients->firstWhere('id', $id);
    }

    public function findOrFail(int $id): Client
    {
        return $this->clients->firstWhere('id', $id)
            ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    public function create(array $data): Client
    {
        $client = new Client($data);
        $client->id = $this->clients->count() + 1;
        $this->clients->push($client);
        return $client;
    }

    public function update(Client $client, array $data): Client
    {
        $client->fill($data);
        return $client;
    }

    public function delete(Client $client): void
    {
        $this->clients = $this->clients->reject(fn ($c) => $c->id === $client->id);
    }

    public function countByStatus(): array
    {
        return $this->clients->groupBy('status')
            ->map->count()
            ->toArray();
    }

    public function activeClients(): Collection
    {
        return $this->clients->where('status', 'active')->values();
    }

    // Helper for tests — seed the fake with data
    public function seed(array $clients): void
    {
        foreach ($clients as $data) {
            $this->clients->push(new Client($data));
        }
    }
}
```

Use the fake in a unit test:

```php
// tests/Unit/ClientServiceTest.php
use App\Repositories\Fakes\FakeClientRepository;
use App\Services\ClientService;

it('creates a client and returns the model', function () {
    $fakeRepo = new FakeClientRepository();
    $service  = new ClientService($fakeRepo);

    $client = $service->create([
        'name'   => 'Test Client',
        'email'  => 'test@example.com',
        'status' => 'active',
    ]);

    expect($client->name)->toBe('Test Client');
    expect($client->email)->toBe('test@example.com');
});

it('returns paginated clients filtered by status', function () {
    $fakeRepo = new FakeClientRepository();
    $fakeRepo->seed([
        ['id' => 1, 'name' => 'Active One',   'email' => 'a@b.com', 'status' => 'active'],
        ['id' => 2, 'name' => 'Inactive One', 'email' => 'c@d.com', 'status' => 'inactive'],
        ['id' => 3, 'name' => 'Active Two',   'email' => 'e@f.com', 'status' => 'active'],
    ]);

    $service = new ClientService($fakeRepo);
    $results = $service->list(status: 'active');

    expect($results->total())->toBe(2);
});
```

Zero database hits. Zero HTTP requests. These tests run in milliseconds.

---

## Step 8 — ProjectRepositoryInterface and Implementation

Create `app/Repositories/Contracts/ProjectRepositoryInterface.php`:

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    public function paginate(
        string $search    = '',
        string $status    = '',
        ?int   $clientId  = null,
        int    $perPage   = 15,
    ): LengthAwarePaginator;

    public function find(int $id): ?Project;

    public function findOrFail(int $id): Project;

    public function create(array $data): Project;

    public function update(Project $project, array $data): Project;

    public function delete(Project $project): void;

    public function overdueProjects(): Collection;

    public function statusBreakdown(): array;

    public function forClient(int $clientId): Collection;
}
```

Create `app/Repositories/Eloquent/EloquentProjectRepository.php`:

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function paginate(
        string $search    = '',
        string $status    = '',
        ?int   $clientId  = null,
        int    $perPage   = 15,
    ): LengthAwarePaginator {
        return Project::query()
            ->with(['client', 'tags'])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($q) => $q->status($status))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): ?Project
    {
        return Project::find($id);
    }

    public function findOrFail(int $id): Project
    {
        return Project::findOrFail($id);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);
        return $project->fresh(['client', 'tags']);
    }

    public function delete(Project $project): void
    {
        // Remove stored attachments before soft-deleting
        $project->attachments->each(function ($attachment) {
            $attachment->deleteFromStorage();
            $attachment->delete();
        });

        $project->delete();
    }

    public function overdueProjects(): Collection
    {
        return Project::overdue()
            ->with('client')
            ->latest('deadline')
            ->get();
    }

    public function statusBreakdown(): array
    {
        return Project::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function forClient(int $clientId): Collection
    {
        return Project::where('client_id', $clientId)
            ->with('tags')
            ->latest()
            ->get();
    }
}
```

---

## Step 9 — Update ProjectService to Use the Repository

```php
<?php

namespace App\Services;

use App\Events\ProjectCreated;
use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    public function create(array $data, array $tagIds = []): Project
    {
        $project = $this->projects->create($data);

        if (! empty($tagIds)) {
            $project->tags()->sync($tagIds);
        }

        ProjectCreated::dispatch($project);

        return $project;
    }

    public function update(Project $project, array $data, array $tagIds = []): Project
    {
        $previousStatus = $project->status;

        $updated = $this->projects->update($project, $data);
        $updated->tags()->sync($tagIds);

        if ($previousStatus !== $updated->status) {
            $updated->loadMissing('client');
            auth()->user()->notify(
                new ProjectStatusChanged($updated, $previousStatus)
            );
        }

        return $updated;
    }

    public function delete(Project $project): void
    {
        $this->projects->delete($project);
    }

    public function overdueProjects(): Collection
    {
        return $this->projects->overdueProjects();
    }

    public function statusBreakdown(): array
    {
        return $this->projects->statusBreakdown();
    }
}
```

---

## Step 10 — Update InvoiceService to Use the Repository

```php
<?php

namespace App\Services;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
    ) {}

    public function create(array $data): Invoice
    {
        return $this->invoices->create($data);
        // create() in the repository handles number generation and recalculate()
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return $this->invoices->update($invoice, $data);
        // update() in the repository handles recalculate()
    }

    public function delete(Invoice $invoice): void
    {
        $this->deletePdf($invoice);
        $this->invoices->delete($invoice);
    }

    public function generatePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'project']);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
                  ->setPaper('a4', 'portrait')
                  ->setOptions([
                      'isHtml5ParserEnabled' => true,
                      'isRemoteEnabled'      => false,
                  ]);

        $path = "invoices/{$invoice->number}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function getPdfContent(Invoice $invoice): string
    {
        if (! $invoice->has_pdf) {
            $this->generatePdf($invoice);
        }

        return Storage::disk('local')->get($invoice->pdf_path);
    }

    public function deletePdf(Invoice $invoice): void
    {
        if ($invoice->pdf_path) {
            Storage::disk('local')->delete($invoice->pdf_path);
            $invoice->update(['pdf_path' => null]);
        }
    }

    public function totalRevenue(): float
    {
        return $this->invoices->totalRevenue();
    }

    public function revenueByMonth(int $months = 12): array
    {
        return $this->invoices->revenueByMonth($months);
    }

    public function overdueInvoices(): \Illuminate\Support\Collection
    {
        return $this->invoices->overdueInvoices();
    }
}
```

---

## Step 11 — Update DashboardService to Use Repositories

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

    public function stats(): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("dashboard_stats_{$workspaceId}", 300, function () {
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
        });
    }

    public function revenueChart(int $months = 12): array
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        return Cache::remember("revenue_chart_{$months}_{$workspaceId}", 300, function () use ($months) {
            $chart       = $this->invoices->revenueByMonth($months);
            $chart['total'] = array_sum($chart['data']);
            return $chart;
        });
    }

    public function projectStatusBreakdown(): array
    {
        return $this->projects->statusBreakdown();
    }

    public function recentActivity(): array
    {
        return [
            'clients'  => Client::latest()->limit(5)->get(),
            'projects' => Project::with('client')->latest()->limit(5)->get(),
            'invoices' => Invoice::with('client')->latest()->limit(5)->get(),
        ];
    }

    public function overdueItems(): array
    {
        return [
            'invoices' => $this->invoices->overdueInvoices()->take(5),
            'projects' => $this->projects->overdueProjects()->take(5),
        ];
    }

    public function bustCache(): void
    {
        $workspaceId = auth()->user()->currentWorkspace()?->id;

        foreach (['dashboard_stats', 'revenue_chart_3', 'revenue_chart_6', 'revenue_chart_12'] as $key) {
            Cache::forget("{$key}_{$workspaceId}");
        }
    }
}
```

---

## Step 12 — Bind All Repositories in AppServiceProvider

The complete `register()` method in `AppServiceProvider` after all changes:

```php
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Eloquent\EloquentClientRepository;
use App\Repositories\Eloquent\EloquentInvoiceRepository;
use App\Repositories\Eloquent\EloquentProjectRepository;
use App\Services\ClientService;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Services\ProjectService;

public function register(): void
{
    // Repository bindings — interface to Eloquent implementation
    $this->app->bind(ClientRepositoryInterface::class,  EloquentClientRepository::class);
    $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
    $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);

    // Service singletons — resolved once per request, dependencies injected automatically
    $this->app->singleton(ClientService::class);
    $this->app->singleton(ProjectService::class);
    $this->app->singleton(InvoiceService::class);
    $this->app->singleton(DashboardService::class);
}
```

Laravel resolves the full dependency tree automatically. When `DashboardService` is resolved, the container sees its constructor needs `ClientRepositoryInterface`, `ProjectRepositoryInterface`, and `InvoiceRepositoryInterface` — and injects `EloquentClientRepository`, `EloquentProjectRepository`, and `EloquentInvoiceRepository` for each. No manual wiring needed.

---

## Complete File Structure After Day 34

```
app/
├── Repositories/
│   ├── Contracts/
│   │   ├── ClientRepositoryInterface.php
│   │   ├── ProjectRepositoryInterface.php
│   │   └── InvoiceRepositoryInterface.php
│   ├── Eloquent/
│   │   ├── EloquentClientRepository.php
│   │   ├── EloquentProjectRepository.php
│   │   └── EloquentInvoiceRepository.php
│   └── Fakes/
│       └── FakeClientRepository.php
├── Services/
│   ├── ClientService.php       ← uses ClientRepositoryInterface
│   ├── ProjectService.php      ← uses ProjectRepositoryInterface
│   ├── InvoiceService.php      ← uses InvoiceRepositoryInterface
│   └── DashboardService.php    ← uses all three repositories
```

---

## When to Use the Repository Pattern

The repository pattern adds a layer of indirection. That indirection has a cost — more files, more interfaces, more mental overhead. Use it deliberately:

| Use repositories | Skip repositories |
|---|---|
| You write unit tests that should not hit the database | You are prototyping and moving fast |
| You want to swap Eloquent for another data source later | Your queries are simple and few |
| Multiple services query the same model in the same way | The team is small and understands the codebase |
| You follow strict SOLID principles in the team | You prefer to keep things lean |

For FreelanceFlow, the pattern earns its keep because the test suite (Phase 3, Day 45+) will run frequently and database tests are slow. The fake repositories mean unit tests stay fast as the app grows.

---

## What We Learned Today

- **Interface defines the contract** — what methods exist and what they return. No implementation details
- **`app->bind(Interface::class, Implementation::class)`** — Laravel injects the implementation wherever the interface is type-hinted. Change the implementation in one line
- **The service no longer imports Eloquent** — `ClientService` only imports `ClientRepositoryInterface`. It is fully decoupled from the database layer
- **Fake repository for unit tests** — implements the same interface using in-memory collections. Tests run in milliseconds with no database dependency
- **`LengthAwarePaginator`** — the concrete class that implements `Illuminate\Contracts\Pagination\LengthAwarePaginator`. The fake uses it directly so pagination tests work the same way as real queries
- **The repository owns queries, the service owns logic** — if a method has business rules it belongs in the service. If it is a data access pattern it belongs in the repository

---

## Day 35 — Logging & Error Tracking

Tomorrow we add proper observability to FreelanceFlow. We will configure Laravel's logging channels — daily rotating files, Slack alerts for critical errors — integrate Sentry for real-time error tracking in production, and add structured log context so every log entry includes workspace ID, user ID, and request metadata. By the end of Day 35 no error in FreelanceFlow goes unnoticed.

See you on Day 35.