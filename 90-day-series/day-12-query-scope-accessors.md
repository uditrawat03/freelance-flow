# Day 12 — Query Scopes & Accessors — Making Eloquent Smarter

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 14 min · **Level:** Beginner to Intermediate

---

> *"Right now the client list returns everything — active, inactive, leads, all mixed together. Filtering by status means writing `where('status', 'active')` inline in the controller. Do that three times across three controllers and you have three places to update when the business rules change. Today we move that logic where it belongs — into the model."*

---

## Where We Are

FreelanceFlow has 50 seeded clients across three statuses. The `ClientController` index method looks like this:

```php
public function index()
{
    $clients = Client::latest()->get();
    return view('clients.index', compact('clients'));
}
```

It works. But as the app grows we will need to filter clients in multiple places — the dashboard, the project assignment form, the invoice recipient picker. Writing `where('status', 'active')` every time is duplication. Change the definition of "active" and you have to find every instance.

Query scopes put that logic in one place. Accessors format data in one place. Today we make the `Client` model do more of the work so everything else does less.

---

## What We Are Building Today

1. **Local query scopes** — filter by status directly on the model
2. **A dynamic scope** — filter by any status, passed as a parameter
3. **Model accessors** — format data before it reaches the view
4. **`Attribute` casting** — type-safe attribute access with Laravel's modern cast API
5. **Apply everything** to the FreelanceFlow client list and controller

---

## Part 1 — Local Query Scopes

A local scope is a method on the model that adds constraints to a query. The naming convention is `scope` + the name you want to call it. Laravel strips the `scope` prefix automatically when you use it.

Open `app/Models/Client.php` and add scopes:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'notes', 'status',
    ];

    // --- Local scopes ---

    // Client::active()->get()
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    // Client::inactive()->get()
    public function scopeInactive(Builder $query): void
    {
        $query->where('status', 'inactive');
    }

    // Client::leads()->get()
    public function scopeLeads(Builder $query): void
    {
        $query->where('status', 'lead');
    }

    // Client::withPhone()->get() — only clients that have a phone number
    public function scopeWithPhone(Builder $query): void
    {
        $query->whereNotNull('phone');
    }

    // Client::withCompany()->get() — only clients attached to a company
    public function scopeWithCompany(Builder $query): void
    {
        $query->whereNotNull('company');
    }
}
```

Now use them in the controller, Tinker, or anywhere else:

```php
// Instead of this — scattered, repeated, fragile
Client::where('status', 'active')->get();
Client::where('status', 'lead')->get();
Client::whereNotNull('phone')->get();

// Use this — readable, centralised, single source of truth
Client::active()->get();
Client::leads()->get();
Client::withPhone()->get();

// Scopes chain with other query methods naturally
Client::active()->latest()->get();
Client::leads()->withPhone()->orderBy('name')->get();
Client::active()->withCompany()->count();
```

Each scope is chainable. They compose exactly like any other Eloquent query builder method because they receive and modify the same `Builder` instance.

---

## Part 2 — Dynamic Scope (Scope with a Parameter)

Sometimes you want one scope that accepts a value rather than three separate scopes. A dynamic scope takes extra parameters after the required `Builder $query` argument:

```php
// Client::status('active')->get()
// Client::status('lead')->get()
// Client::status('inactive')->get()
public function scopeStatus(Builder $query, string $status): void
{
    $query->where('status', $status);
}
```

This is useful when the status comes from user input — a filter dropdown, a URL parameter, a query string. Instead of a `match()` block switching between scopes, you pass the value directly:

```php
// In the controller — status comes from the request
$status  = request('status', 'active'); // default to active
$clients = Client::status($status)->latest()->get();
```

Use both styles — named scopes (`Client::active()`) for commonly used, fixed filters, and the dynamic scope (`Client::status($value)`) when the filter value is variable.

---

## Part 3 — Applying Scopes to the Client List

Update the `ClientController` to support filtering from the URL:

```php
// app/Http/Controllers/ClientController.php
use App\Models\Client;

class ClientController extends Controller
{
    public function index()
    {
        $status  = request('status'); // null = show all
        $search  = request('search');

        $clients = Client::query()
            ->when($status, fn ($q) => $q->status($status))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            }))
            ->latest()
            ->get();

        return view('clients.index', compact('clients', 'status', 'search'));
    }
}
```

**`when()` is one of the most important Eloquent methods.** It conditionally applies a query constraint only when the first argument is truthy. When `$status` is null (no filter selected), the `status()` scope is skipped entirely. When it has a value, the scope fires. No `if` statements, no conditional query building — just clean, readable method chaining.

Add a simple filter bar to `resources/views/clients/index.blade.php` above the client list:

```blade
{{-- Filter bar --}}
<div class="flex items-center gap-3 mb-5">

    {{-- Status filters --}}
    <div class="flex items-center gap-2">
        @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'lead' => 'Leads'] as $value => $label)
            <a
                href="{{ route('clients.index', array_filter(['status' => $value === 'all' ? null : $value, 'search' => $search])) }}"
                class="text-sm px-3 py-1.5 rounded-full border transition-colors
                    {{ ($status ?? 'all') === $value
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'text-gray-600 border-gray-200 hover:border-indigo-300' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('clients.index') }}" class="flex items-center gap-2 ml-auto">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <input
            type="text"
            name="search"
            value="{{ $search }}"
            placeholder="Search clients..."
            class="text-sm border border-gray-200 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-300 w-48"
        >
        <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
            Search
        </button>
        @if ($search)
            <a href="{{ route('clients.index', ['status' => $status]) }}" class="text-sm text-gray-400 hover:text-gray-600">
                Clear
            </a>
        @endif
    </form>

</div>
```

Now `/clients?status=active` shows only active clients. `/clients?status=lead&search=acme` shows leads matching "acme". The filter state is preserved across status switches because each filter link carries the current search value.

---

## Part 4 — Model Accessors

An accessor is a method on the model that transforms an attribute value before it is returned. You define it using the `Attribute` class from Laravel's modern accessor API.

Add these accessors to the `Client` model:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

class Client extends Model
{
    // ...scopes above...

    // --- Accessors ---

    // $client->display_name
    // Returns company name if available, otherwise personal name
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company
                ? "{$this->name} ({$this->company})"
                : $this->name,
        );
    }

    // $client->initials
    // First letter of each word in the name — for avatars
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(explode(' ', $this->name))
                ->map(fn ($word) => strtoupper($word[0]))
                ->take(2)
                ->implode(''),
        );
    }

    // $client->status_label
    // Human-readable, title-cased status label
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'active'   => 'Active',
                'inactive' => 'Inactive',
                'lead'     => 'Lead',
                default    => ucfirst($this->status),
            },
        );
    }

    // $client->is_active
    // Boolean convenience accessor
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'active',
        );
    }
}
```

Use them in the client list view — no logic in the view, just clean property access:

```blade
{{-- Before: logic in the view --}}
<p>{{ $client->company ? $client->name . ' (' . $client->company . ')' : $client->name }}</p>

{{-- After: clean property access --}}
<p>{{ $client->display_name }}</p>

{{-- Avatar with initials --}}
<div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center">
    <span class="text-xs font-semibold text-indigo-700">{{ $client->initials }}</span>
</div>

{{-- Status label --}}
<span>{{ $client->status_label }}</span>

{{-- Conditional rendering --}}
@if ($client->is_active)
    <span class="text-green-600">●</span>
@endif
```

Accessors are cached per model instance — `$client->display_name` is only computed once per request, no matter how many times you access it.

---

## Part 5 — Mutators (Writing Back to the Model)

A mutator transforms a value before it is saved to the database. Define both getter and setter on the same `Attribute`:

```php
// Ensure name is always stored in title case
protected function name(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => $value,
        set: fn (string $value) => str($value)->title()->toString(),
    );
}

// Ensure email is always stored in lowercase
protected function email(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => $value,
        set: fn (string $value) => strtolower(trim($value)),
    );
}

// Trim whitespace from company name on save
protected function company(): Attribute
{
    return Attribute::make(
        get: fn (?string $value) => $value,
        set: fn (?string $value) => $value ? trim($value) : null,
    );
}
```

Now when the Livewire `save()` method calls `Client::create([...])`, the mutators fire automatically. The user can type "  ACME CORP  " and it stores as "Acme Corp". The user can type "HELLO@ACME.COM" and it stores as "hello@acme.com". No controller logic needed — the model handles its own data integrity.

---

## Part 6 — Attribute Casting

Casting tells Eloquent to automatically convert a database value to a PHP type when reading, and back when writing. Add the `$casts` property to the model:

```php
protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
];
```

With datetime casting, you can use Carbon methods directly on timestamps:

```blade
{{-- Without casting: string, needs manual parsing --}}
{{ \Carbon\Carbon::parse($client->created_at)->diffForHumans() }}

{{-- With casting: already a Carbon instance --}}
{{ $client->created_at->diffForHumans() }}   {{-- "3 days ago" --}}
{{ $client->created_at->format('M d, Y') }}  {{-- "Jan 15, 2026" --}}
```

Add the created date to the client list card:

```blade
<p class="text-xs text-gray-400 mt-0.5">
    Added {{ $client->created_at->diffForHumans() }}
</p>
```

---

## The Updated Client Model — Everything Together

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'notes', 'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // --- Scopes ---

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('status', 'inactive');
    }

    public function scopeLeads(Builder $query): void
    {
        $query->where('status', 'lead');
    }

    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeWithPhone(Builder $query): void
    {
        $query->whereNotNull('phone');
    }

    public function scopeWithCompany(Builder $query): void
    {
        $query->whereNotNull('company');
    }

    // --- Accessors ---

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company
                ? "{$this->name} ({$this->company})"
                : $this->name,
        );
    }

    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(explode(' ', $this->name))
                ->map(fn ($word) => strtoupper($word[0]))
                ->take(2)
                ->implode(''),
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'active'   => 'Active',
                'inactive' => 'Inactive',
                'lead'     => 'Lead',
                default    => ucfirst($this->status),
            },
        );
    }

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'active',
        );
    }

    // --- Mutators ---

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => str($value)->title()->toString(),
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => strtolower(trim($value)),
        );
    }

    protected function company(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: fn (?string $value) => $value ? trim($value) : null,
        );
    }
}
```

---

## Quick Reference

```php
// --- Scopes ---
Client::active()->get()                    // WHERE status = 'active'
Client::leads()->latest()->get()           // WHERE status = 'lead' ORDER BY created_at DESC
Client::status('inactive')->count()        // WHERE status = 'inactive'
Client::active()->withCompany()->get()     // WHERE status = 'active' AND company IS NOT NULL
Client::query()->when($status, fn($q) => $q->status($status))->get()

// --- Accessors (read-only computed properties) ---
$client->display_name     // "Jane Smith (Acme Corp)" or "Jane Smith"
$client->initials         // "JS"
$client->status_label     // "Active"
$client->is_active        // true or false
$client->created_at->diffForHumans()  // "3 days ago" (needs datetime cast)

// --- Mutators fire automatically on save ---
Client::create(['name' => '  jane smith  ']); // stored as "Jane Smith"
Client::create(['email' => 'HELLO@ACME.COM']); // stored as "hello@acme.com"
```

---

## What We Learned Today

- **Local query scopes** — `scopeActive()` becomes `Client::active()`. One place to define, use everywhere
- **Dynamic scopes** — accept parameters for variable filters like status from URL query strings
- **`when()`** — conditionally apply query constraints without `if` statements. Clean chaining
- **Accessors** — compute and format attributes on the model using the modern `Attribute::make()` API
- **Mutators** — clean and transform data before saving using the `set:` callback on `Attribute::make()`
- **`$casts`** — convert database values to PHP types. `datetime` gives you Carbon methods automatically
- **Scopes compose** — chain multiple scopes together like any other Eloquent builder method
- **Accessors are cached** — computed once per model instance, no performance penalty for multiple accesses

---

## Day 13 — Pagination & Search

Tomorrow we add proper pagination to the client list. Right now `get()` loads all 50 clients at once. In production FreelanceFlow might have 5,000 clients. We will swap `get()` for `paginate()`, add Livewire-powered live search that updates the list as you type, and integrate the filter bar from today with the paginator — so filters and search survive across pages.

See you on Day 13.