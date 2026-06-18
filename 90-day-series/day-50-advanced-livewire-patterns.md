# Day 50 - Advanced Livewire Patterns

> **Series:** FreelanceFlow - Laravel Zero to Hero  
> **Phase:** Advanced  
> **Read time:** 14 min  
> **Level:** Intermediate

---

FreelanceFlow already uses Livewire for forms, filters, pagination, and dashboard updates. Day 50 upgrades those patterns so the app stays fast and maintainable as the amount of workspace data grows.

The focus is not flashy UI. The focus is scalable Livewire:

- lazy components for expensive summary data
- service/repository boundaries instead of query-heavy components
- event-driven refreshes between sibling components
- scoped loading states that do not flicker
- `wire:navigate` links for faster internal navigation
- workspace-safe validation for quick actions

---

## What Changed

### New files

- `app/Livewire/Clients/Stats.php`
- `resources/views/livewire/clients/stats.blade.php`
- `resources/views/livewire/clients/stats-placeholder.blade.php`
- `app/Livewire/Invoices/QuickCreate.php`
- `resources/views/livewire/invoices/quick-create.blade.php`
- `tests/Feature/AdvancedLivewirePatternsTest.php`

### Modified files

- `app/Services/ClientService.php`
- `app/Services/InvoiceService.php`
- `app/Livewire/Invoices/InvoiceList.php`
- `resources/views/livewire/clients/client-list.blade.php`
- `resources/views/livewire/invoices/invoice-list.blade.php`
- `lang/en/app.php`
- `lang/hi/app.php`

---

## Pattern 1 - Lazy Client Stats

The client list should render quickly even if summary metrics become expensive later. The new stats component is lazy-loaded and uses the existing `ClientService::statistics()` cache path.

```php
// app/Livewire/Clients/Stats.php

namespace App\Livewire\Clients;

use App\Services\ClientService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class Stats extends Component
{
    public function placeholder(): View
    {
        return view('livewire.clients.stats-placeholder');
    }

    public function render(ClientService $clientService): View
    {
        $stats = $clientService->statistics();

        return view('livewire.clients.stats', [
            'stats' => [
                'total' => array_sum($stats),
                'active' => $stats['active'] ?? 0,
                'inactive' => $stats['inactive'] ?? 0,
                'lead' => $stats['lead'] ?? 0,
            ],
        ]);
    }
}
```

The component is included above the client list:

```blade
<livewire:clients.stats />
```

The important scalability detail: the component asks the service for data. It does not build dashboard-style queries in Blade or in the parent list component.

---

## Pattern 2 - Service-Level Invoice Pagination

`InvoiceList` now delegates pagination to `InvoiceService`, which delegates to the repository:

```php
public function list(string $status = '', int $perPage = 15): LengthAwarePaginator
{
    return $this->invoices->paginate($status, $perPage);
}
```

That keeps the Livewire component thin:

```php
public function render(InvoiceService $invoiceService)
{
    $invoices = $invoiceService->list($this->status, 15);

    return view('livewire.invoices.invoice-list', compact('invoices'));
}
```

This matters when invoice filtering grows to include date ranges, clients, payment state, search, team ownership, or saved views. The component API can stay stable while the repository evolves.

---

## Pattern 3 - Quick Create with Events

The invoice index now has a quick-create Livewire component beside the full "New invoice" link.

When a quick invoice is created:

1. `QuickCreate` validates the input.
2. `InvoiceService::create()` creates the invoice and busts invoice/dashboard caches.
3. `QuickCreate` dispatches `invoice-created`.
4. `InvoiceList` listens for that event and resets to page 1.

```php
#[On('invoice-created')]
public function refreshList(): void
{
    $this->resetPage();
}
```

This is sibling component communication without a JavaScript event bus.

---

## Pattern 4 - Workspace-Safe Validation

A plain `exists:clients,id` rule is not enough in a multi-workspace app because it checks the table, not the user's active workspace.

The quick-create component uses a scoped existence rule:

```php
$this->validate([
    'client_id' => [
        'required',
        Rule::exists('clients', 'id')->where(
            'workspace_id',
            auth()->user()->currentWorkspace()?->id,
        ),
    ],
    'description' => ['required', 'string', 'max:255'],
    'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
]);
```

This prevents crafted Livewire requests from creating invoices against another workspace's client.

---

## Pattern 5 - Targeted Loading States

The client list now scopes loading styles to the interactions that actually refresh the list:

```blade
<div wire:loading.delay.long wire:target="search,status,setStatus,clearSearch">
    Searching...
</div>

<div wire:loading.class="opacity-50" wire:target="search,status,setStatus,clearSearch">
    ...
</div>
```

`delay.long` avoids spinner flicker on fast requests. `wire:target` avoids unrelated Livewire updates triggering the list overlay.

---

## Pattern 6 - wire:navigate for Internal Links

The client and invoice create/edit links now use `wire:navigate`:

```blade
<a href="{{ route('clients.create') }}" wire:navigate>
    + New client
</a>
```

Use `wire:navigate.persist` carefully. The current sidebar calculates active classes from the current route, so persisting it would keep stale active navigation after page changes. In this app, plain `wire:navigate` is the safer default.

---

## Tests Added

`tests/Feature/AdvancedLivewirePatternsTest.php` covers:

- client stats render only the current workspace counts
- quick invoice creation stores a draft invoice with calculated totals
- quick invoice creation dispatches `invoice-created` and `notify`
- quick invoice creation rejects clients from other workspaces

Run the checks:

```bash
php artisan test
vendor/bin/pint
```

---

## What We Learned

- Lazy components are best for below-the-fold or independently expensive data.
- Livewire components should orchestrate UI state, not own complex query logic.
- Services are a stable scaling point for cache busting and repository calls.
- Livewire events are enough for many sibling component refresh flows.
- In multi-tenant apps, validation rules must include tenant/workspace scope.
- Loading indicators feel better when scoped to the exact action that needs feedback.
- `wire:navigate` improves perceived speed, but persisted layout pieces must not depend on route-derived active state.

---

## Day 51 Preview

Next we can take these same ideas into richer dashboard decomposition: separate lazy panels, stronger cache keys, and smaller chart components that refresh independently.
