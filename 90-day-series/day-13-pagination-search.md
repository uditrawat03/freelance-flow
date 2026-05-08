# Day 13 — Pagination & Search — Livewire-Powered Live Search

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 15 min · **Level:** Beginner to Intermediate

---

> *"Right now `Client::latest()->get()` loads every client in the database on every page load. With 50 records that is fine. With 5,000 it is a timeout waiting to happen. Today we fix that with pagination — and then we build something better than a submit-button search: a Livewire component that filters the list as you type, in real time, with zero page reloads."*

---

## Where We Are

The client list at `/clients` has a filter bar and a search form from Day 12. Both work — but they require a full page reload on every interaction. The search requires hitting Submit. Changing the status filter navigates to a new URL.

That is functional, but it is not the experience FreelanceFlow deserves.

Today we replace the static filter bar and search form with a single Livewire component that:

- Paginates the client list so only 10 records load at a time
- Searches by name, email, or company as the user types — no submit button
- Filters by status instantly when a pill is clicked
- Preserves all state in the URL so the page is shareable and the browser back button works

---

## What We Are Building Today

1. **Eloquent pagination** — swap `get()` for `paginate()` on the controller
2. **A `ClientList` Livewire component** — owns search, filter, and pagination state
3. **Live search** with debounce — wait until typing stops before querying
4. **URL query string sync** — filter and search state survives page refresh and is shareable
5. **Updating the client list view** to use the Livewire component

---

## Step 1 — Basic Pagination First

Before adding Livewire, understand how Eloquent pagination works on its own. Swap `get()` for `paginate()` in the controller:

```php
// app/Http/Controllers/ClientController.php
public function index()
{
    $clients = Client::latest()->paginate(10);
    return view('clients.index', compact('clients'));
}
```

`paginate(10)` returns a `LengthAwarePaginator` — a special collection that knows the current page, total records, and how many pages exist. Add the pagination links to the view:

```blade
{{-- At the bottom of the client list --}}
{{ $clients->links() }}
```

That single tag renders full pagination links styled with Tailwind by default in modern Laravel. Visit `/clients?page=2` — page 2 of clients loads.

This works. But every page navigation and every filter change is still a full page reload. We can do much better.

---

## Step 2 — Create the ClientList Livewire Component

Generate the component:

```bash
php artisan make:livewire ClientList
```

This creates:
- `app/Livewire/ClientList.php`
- `resources/views/livewire/client-list.blade.php`

---

## Step 3 — Build the Component Class

Open `app/Livewire/ClientList.php`:

```php
<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ClientList extends Component
{
    use WithPagination;

    // #[Url] syncs the property to the URL query string automatically
    // The browser URL updates as the user searches or filters
    // The state survives page refresh and is shareable
    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    // Reset pagination to page 1 whenever search changes
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // Reset pagination to page 1 whenever status filter changes
    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        // Toggle off if same status clicked again
        $this->status = $this->status === $status ? '' : $status;
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function render()
    {
        $clients = Client::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                      ->orWhere('company', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status, fn ($query) => $query->status($this->status))
            ->latest()
            ->paginate(10);

        return view('livewire.clients.client-list', [
            'clients' => $clients,
        ]);
    }
}
```

**What each piece does:**

- `use WithPagination` — Livewire's pagination trait. Handles page state, `resetPage()`, and renders Tailwind-styled paginator links
- `#[Url(history: true)]` — syncs the property to the URL query string. When `$search` changes, the URL updates to `?search=acme`. `history: true` pushes each state change to the browser history stack — the back button works correctly
- `updatedSearch()` — Livewire calls this automatically when `$search` changes. We reset to page 1 so the user does not land on page 3 of results for a new search term
- `setStatus()` — toggles the status filter. Clicking the same pill twice clears the filter
- The query in `render()` uses the same `when()` + scope pattern from Day 13 — it now runs reactively every time any property changes

---

## Step 4 — Build the Component View

Open `resources/views/livewire/client-list.blade.php`:

```blade
<div>

    {{-- Search and filter bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">

        {{-- Live search input --}}
        <div class="relative flex-1">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search by name, email or company..."
                class="w-full text-sm border border-gray-200 rounded-lg pl-9 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
            {{-- Search icon --}}
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
            </svg>
            {{-- Clear search button --}}
            @if ($search)
                <button
                    wire:click="clearSearch"
                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600"
                    aria-label="Clear search"
                >
                    &times;
                </button>
            @endif
        </div>

        {{-- Status filter pills --}}
        <div class="flex items-center gap-2 flex-shrink-0">
            @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'lead' => 'Leads'] as $value => $label)
                <button
                    wire:click="setStatus('{{ $value }}')"
                    class="text-sm px-3 py-1.5 rounded-full border transition-colors
                        {{ $status === $value
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'text-gray-600 border-gray-200 hover:border-indigo-300 bg-white' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

    </div>

    {{-- Result count --}}
    <p class="text-xs text-gray-400 mb-3">
        {{ $clients->total() }} {{ Str::plural('client', $clients->total()) }}
        @if ($search)
            matching <span class="font-medium text-gray-600">"{{ $search }}"</span>
        @endif
        @if ($status)
            · <span class="font-medium text-gray-600">{{ ucfirst($status) }}</span> only
        @endif
    </p>

    {{-- Loading indicator --}}
    <div wire:loading.delay class="text-xs text-gray-400 mb-2">
        Searching...
    </div>

    {{-- Client list --}}
    <div wire:loading.class="opacity-50">

        @forelse ($clients as $client)
            <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-2 flex items-center justify-between transition-opacity">

                {{-- Avatar + info --}}
                <div class="flex items-center gap-3">
                    {{-- Initials avatar --}}
                    <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-semibold text-indigo-700">
                            {{ $client->initials }}
                        </span>
                    </div>

                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $client->display_name }}</p>
                        <p class="text-xs text-gray-500">{{ $client->email }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Added {{ $client->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>

                {{-- Right: badge + edit --}}
                <div class="flex items-center gap-4">
                    <x-client-status :status="$client->status" />
                    <a
                        href="{{ route('clients.edit', $client) }}"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        Edit
                    </a>
                </div>

            </div>
        @empty
            <x-empty-state
                message="{{ $search || $status ? 'No clients match your search.' : 'No clients yet.' }}"
                cta-text="{{ !$search && !$status ? 'Add your first client' : '' }}"
                :cta-href="!$search && !$status ? route('clients.create') : ''"
            />
        @endforelse

    </div>

    {{-- Pagination --}}
    @if ($clients->hasPages())
        <div class="mt-4">
            {{ $clients->links() }}
        </div>
    @endif

</div>
```

**Key details in the view:**

- `wire:model.live.debounce.300ms="search"` — the search input is live but debounced by 300 milliseconds. Livewire waits until the user stops typing for 300ms before triggering a query. Without debounce, every single keystroke fires a database query — wasteful and laggy on slow connections
- `wire:loading.delay` on the "Searching..." text — `.delay` adds a small delay before showing the loading indicator. This prevents a flash of "Searching..." for very fast queries that complete in under 200ms
- `wire:loading.class="opacity-50"` on the list wrapper — fades the list slightly while new results are loading. Subtle visual feedback that something is happening
- `$clients->hasPages()` — only renders the paginator when there is more than one page. No empty paginator on small lists
- The empty state message is context-aware — "No clients match your search" when filters are active, "No clients yet" when the list is genuinely empty

---

## Step 5 — Update the Client List View

Replace the entire content of `resources/views/clients/index.blade.php`. The Livewire component now owns the search, filter, and list — the Blade view just provides the page header and the Add Client button:

```blade
@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    <x-page-header title="Clients" subtitle="Manage all your clients in one place.">
        <a
            href="{{ route('clients.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors"
        >
            + Add client
        </a>
    </x-page-header>

    {{-- Livewire component owns search, filters, and the list --}}
    <livewire:client-list />

@endsection
```

The Blade view is now five lines of content. The Livewire component handles everything dynamic.

---

## Step 6 — Update the Controller

The controller's `index()` method is now almost empty — the Livewire component does the querying:

```php
// app/Http/Controllers/ClientController.php
public function index()
{
    return view('clients.index');
}
```

No `$clients` variable, no query, no `compact()`. The Livewire component handles all of that inside its own `render()` method.

---

## Step 7 — Configure Livewire Pagination Styling

By default, Livewire uses Tailwind-styled pagination links. If the links look unstyled, publish the Livewire pagination views:

```bash
php artisan livewire:publish --pagination
```

This copies the pagination views to `resources/views/vendor/livewire/`. You can customise them if needed, but the defaults are clean and functional out of the box.

---

## How `#[Url]` Works — The URL Sync Magic

The `#[Url]` attribute is what makes this component genuinely useful rather than just interactive.

Without `#[Url]`:
- User searches for "acme" → list filters to 3 results
- User copies the URL → URL is still `/clients` → friend opens it and sees all 50 clients
- User refreshes → search is gone, back to all clients
- Browser back button → does not undo the search

With `#[Url(history: true)]`:
- User searches for "acme" → URL updates to `/clients?search=acme`
- User copies the URL → friend opens `/clients?search=acme` → sees the same 3 results
- User refreshes → search persists, "acme" is still in the input
- Browser back button → undoes the search, URL reverts to `/clients`

```php
// Different Url options
#[Url]                     // syncs to URL, no history entry
#[Url(history: true)]      // syncs + pushes to browser history stack
#[Url(as: 'q')]            // syncs as ?q= instead of ?search=
#[Url(except: '')]         // exclude default value from URL (clean URLs)
```

For FreelanceFlow we use `history: true` so the back button works correctly when users navigate from the client list to an edit page and back.

---

## Debounce Reference

```blade
{{-- Fire immediately on every keystroke --}}
wire:model.live="search"

{{-- Wait 300ms after last keystroke (recommended for search) --}}
wire:model.live.debounce.300ms="search"

{{-- Wait 500ms (for slower connections or heavier queries) --}}
wire:model.live.debounce.500ms="search"

{{-- Only fire when user leaves the field --}}
wire:model.blur="search"

{{-- Only fire on form submit --}}
wire:model="search"
```

300ms is the right debounce for most search inputs. It feels instant to the user but eliminates query spam during fast typing.

---

## Test the Full Experience

Run the dev server and walk through the complete flow:

```bash
npm run dev
php artisan serve
```

**Live search:**
Visit `/clients` — type "acme" in the search box. The list updates after 300ms without any button click. The URL changes to `/clients?search=acme`. Clear the search — all clients return.

**Status filtering:**
Click "Active" — only active clients appear. The URL updates to `/clients?status=active`. Click "Active" again — filter clears. Click "Leads" with a search term still active — both filters apply simultaneously.

**Pagination:**
With 50 clients and `paginate(10)`, there are 5 pages. Navigate to page 3. The URL shows `?page=3`. Search for something — pagination resets to page 1 automatically.

**URL sharing:**
Filter to active leads named "smith": `/clients?search=smith&status=active`. Copy that URL, open a new tab, paste it — the same filtered, searched list appears with the same UI state.

**Browser back button:**
From the client list, click Edit on a client. Click the browser back button — you land back on the client list with the same search and filter state you had before.

---

## What We Learned Today

- **`paginate(10)`** returns a `LengthAwarePaginator` with built-in page state, total counts, and Tailwind-styled links via `{{ $clients->links() }}`
- **`use WithPagination`** — Livewire's pagination trait. `resetPage()` resets to page 1 on filter changes
- **`#[Url(history: true)]`** — syncs component state to the URL query string and pushes browser history entries. Back button, page refresh, and URL sharing all work correctly
- **`wire:model.live.debounce.300ms`** — live binding with 300ms debounce. Queries fire when typing pauses, not on every keystroke
- **`wire:loading.delay`** — shows loading indicator only if the response takes longer than ~200ms. Prevents flicker on fast queries
- **`wire:loading.class="opacity-50"`** — applies a CSS class to an element while any Livewire request is in flight
- **Context-aware empty states** — different messages when results are empty due to filters vs genuinely empty
- **`$clients->hasPages()`** — conditionally render the paginator only when there is more than one page

---


## Day 14 — Flash Messages & Sessions

Tomorrow we go deeper on sessions and flash messages. We will cover:

- Multiple flash message types — success, error, warning, info — with different styles
- Stacking multiple flash messages in a single request
- Session persistence across redirects
- Using `session()->keep()` to persist flash data for longer
- Integrating flash messages with Livewire's `dispatch()` for in-component notifications

See you on Day 14.