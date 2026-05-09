# Day 18 — Eager Loading & the N+1 Problem

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"FreelanceFlow's client show page loads a client, then loops through their projects, and for each project loads the tags. With 10 projects that is 12 database queries. With 100 projects that is 102. This is the N+1 problem — and it is silent. No error, no warning, just a page that gets slower as the database grows. Today we find it, understand it, and eliminate it permanently."*

---

## What Is the N+1 Problem?

The name comes from the query count: 1 query to load the parent records, then N queries — one per record — to load the related data.

Here is the problem hiding in FreelanceFlow right now:

```php
// ClientController show() method
$client->load('projects');

// Then in the view:
@foreach ($client->projects as $project)
    @foreach ($project->tags as $tag)  // ← this hits the database once per project
        {{ $tag->name }}
    @endforeach
@endforeach
```

What actually happens at the database level:

```sql
-- Query 1: load the client
SELECT * FROM clients WHERE id = 1;

-- Query 2: load the client's projects
SELECT * FROM projects WHERE client_id = 1;

-- Query 3: load tags for project 1
SELECT * FROM tags INNER JOIN project_tag ON ... WHERE project_tag.project_id = 1;

-- Query 4: load tags for project 2
SELECT * FROM tags INNER JOIN project_tag ON ... WHERE project_tag.project_id = 2;

-- Query 5: load tags for project 3
-- ... and so on for every project
```

10 projects = 12 queries. 100 projects = 102 queries. 1,000 projects = 1,002 queries. The page time grows linearly with data. This is how Laravel applications quietly become slow in production.

---

## What We Are Building Today

1. **Install Laravel Debugbar** to see queries in real time
2. **Reproduce the N+1** visually in the browser
3. **Fix with `with()`** — eager load at query time
4. **Fix with `load()`** — eager load after the fact
5. **`withCount()`** — count relationships without loading records
6. **Nested eager loading** — `with('projects.tags')`
7. **Apply fixes** across all FreelanceFlow pages that have relationships

---

## Step 1 — Install Laravel Debugbar

Debugbar is a development tool that shows a toolbar at the bottom of every page displaying queries, models, routes, views, and performance data. It is the fastest way to find N+1 problems.

```bash
composer require barryvdh/laravel-debugbar --dev
```

That is the entire installation. Debugbar auto-discovers itself and only activates when `APP_DEBUG=true` in your `.env` — which is the default for local development.

Refresh any page in FreelanceFlow. You will see a toolbar at the bottom of the browser. Click the **Queries** tab. Every SQL query is listed with its execution time.

Now visit the client show page for a client with 10 projects. Count the queries in the Debugbar panel. You will see 12 or more — one for the client, one for projects, then one per project for tags. That is your N+1.

---

## Step 2 — Fix with `with()` — Eager Loading at Query Time

The fix is to tell Laravel upfront which relationships you need. It then loads all of them in the minimum number of queries — regardless of how many records are involved.

Open `app/Http/Controllers/ClientController.php` and update the `show()` method:

```php
public function show(Client $client)
{
    // Before: N+1 problem
    // $client->load('projects');

    // After: eager load projects AND their tags in one go
    $client->load(['projects' => function ($query) {
        $query->with('tags')->latest();
    }]);

    return view('clients.show', compact('client'));
}
```

Or more concisely using dot notation:

```php
public function show(Client $client)
{
    // Load projects and their tags together
    $client->load('projects.tags');

    return view('clients.show', compact('client'));
}
```

Now refresh the client show page. Open Debugbar Queries. You will see exactly **3 queries** regardless of how many projects or tags the client has:

```sql
-- Query 1: the client (resolved by route model binding)
SELECT * FROM clients WHERE id = 1 LIMIT 1;

-- Query 2: all projects for this client
SELECT * FROM projects WHERE client_id = 1;

-- Query 3: all tags for those projects (IN clause, not a loop)
SELECT tags.*, project_tag.project_id
FROM tags
INNER JOIN project_tag ON tags.id = project_tag.tag_id
WHERE project_tag.project_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
```

The third query uses an `IN` clause containing all project IDs at once. Laravel collects the IDs from query 2, fires one query with all of them, then maps the results back to the correct project instances in PHP. This is eager loading — it cannot get faster than 3 queries for this data structure.

---

## Step 3 — `with()` on the Initial Query

When you are loading a list of records and know you will need related data, use `with()` on the initial query instead of `load()` afterwards.

```php
// Loading a list of clients with their projects and project counts
$clients = Client::with('projects')->latest()->paginate(10);

// Loading clients with nested relationships — projects and their tags
$clients = Client::with('projects.tags')->latest()->paginate(10);

// Multiple separate relationships at once
$clients = Client::with(['projects', 'invoices'])->latest()->paginate(10);

// Multiple relationships with constraints
$clients = Client::with([
    'projects' => fn ($q) => $q->active()->latest()->limit(3),
    'projects.tags',
])->latest()->paginate(10);
```

**`with()` vs `load()`:**

| Method | When to use |
|---|---|
| `with()` | On the initial query — when building a list |
| `load()` | After a record is already in memory — when adding relationships to an existing model |

In a controller that loads a single record via route model binding, `load()` is cleaner because the model is already resolved:

```php
// Route model binding already resolved $client
// Use load() to add relationships
public function show(Client $client)
{
    $client->load('projects.tags');
    return view('clients.show', compact('client'));
}
```

In a controller that runs its own query, use `with()`:

```php
// Running our own query — use with()
public function index()
{
    $clients = Client::with('activeProjects')->active()->paginate(10);
    return view('clients.index', compact('clients'));
}
```

---

## Step 4 — `withCount()` — Count Without Loading

A common UI pattern: show the number of projects a client has in the list. Without eager loading you would either load all projects to count them (expensive) or run a separate query per client (N+1).

`withCount()` adds a COUNT subquery to the main query and makes the result available as a property:

```php
// Get clients with a project count — 1 query total
$clients = Client::withCount('projects')->latest()->paginate(10);
```

Now each client model has a `projects_count` property:

```blade
<p class="text-xs text-gray-400">
    {{ $client->projects_count }} {{ Str::plural('project', $client->projects_count) }}
</p>
```

You can combine `with()` and `withCount()`:

```php
// Load active projects AND count all projects
$clients = Client::with('activeProjects')
                 ->withCount('projects')
                 ->withCount(['projects as active_projects_count' => fn ($q) => $q->active()])
                 ->latest()
                 ->paginate(10);
```

The aliased count `active_projects_count` is available as `$client->active_projects_count`.

---

## Step 5 — Update the ClientList Livewire Component

The live search component loads all clients without any relationship data. Update it to include project counts:

```php
// app/Livewire/ClientList.php

public function render()
{
    $clients = Client::query()
        ->withCount('projects')  // adds projects_count to each client
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

    return view('livewire.client-list', [
        'clients' => $clients,
    ]);
}
```

Update the client card in `resources/views/livewire/client-list.blade.php`:

```blade
<div>
    <a href="{{ route('clients.show', $client) }}"
       class="font-medium text-gray-900 text-sm hover:text-indigo-600 transition-colors">
        {{ $client->display_name }}
    </a>
    <p class="text-xs text-gray-500">{{ $client->email }}</p>
    <p class="text-xs text-gray-400 mt-0.5">
        {{ $client->projects_count }} {{ Str::plural('project', $client->projects_count) }}
        · Added {{ $client->created_at->diffForHumans() }}
    </p>
</div>
```

One query addition — `withCount('projects')` — and every client card now shows their project count with zero extra queries.

---

## Step 6 — Check the Dashboard for N+1

The dashboard will eventually show aggregate data — total clients, active projects, total revenue. Make sure those are aggregates, not loops:

```php
// ❌ N+1 — loads all clients then counts in PHP
$clientCount = Client::all()->count();

// ✓ Single COUNT query
$clientCount = Client::count();

// ✓ Multiple counts in separate efficient queries
$stats = [
    'clients'          => Client::active()->count(),
    'projects'         => Project::active()->count(),
    'overdue_projects' => Project::overdue()->count(),
];
```

Never call `->all()` just to count. `->count()` generates `SELECT COUNT(*) FROM ...` which is instantaneous even on large tables.

---

## Step 7 — Preventive Measure: Strict Mode

Laravel has a strict mode setting that throws an exception whenever a lazy-loaded relationship is accessed — catching N+1 problems during development before they reach production.

Add this to `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Throws an exception if a relationship is lazy-loaded (not eager-loaded)
    // Only enable in local/testing environments
    Model::preventLazyLoading(! app()->isProduction());
}
```

With this enabled, accessing `$project->tags` without having eager-loaded `tags` throws:

```
Attempted to lazy load [tags] on model [App\Models\Project]
but lazy loading is disabled.
```

This forces you to add `with('tags')` or `load('tags')` explicitly. Every N+1 becomes an error you cannot miss during development.

> Disable this in production — `! app()->isProduction()` handles that automatically. In production, lazy loading silently works so a missed eager load does not crash the app for real users. You catch it in development and fix it before deploying.

---

## Eager Loading Reference

```php
// with() — on the initial query
Client::with('projects')->get();
Client::with('projects.tags')->get();          // nested
Client::with(['projects', 'invoices'])->get(); // multiple

// with() + constraints
Client::with(['projects' => fn($q) => $q->active()->latest()->limit(5)])->get();

// load() — after the model is in memory
$client->load('projects');
$client->load('projects.tags');
$client->load(['projects', 'invoices']);

// loadMissing() — only load if not already loaded
$client->loadMissing('projects');

// withCount() — count without loading records
Client::withCount('projects')->get();           // $client->projects_count
Client::withCount(['projects as active_count'
    => fn($q) => $q->active()])->get();         // $client->active_count

// Combine with() and withCount()
Client::with('activeProjects')->withCount('projects')->paginate(10);

// Always avoid this:
Client::all()->count();    // ❌ loads everything, counts in PHP
Client::count();           // ✓ COUNT(*) in SQL
```

---

## Debugbar Query Tab — What to Look For

After installing Debugbar, open any page and look for these warning signs in the Queries tab:

| Warning sign | What it means | Fix |
|---|---|---|
| Same table queried in a loop | N+1 on a relationship | Add `with()` or `load()` |
| `SELECT *` on a table with 20+ columns | Loading unused columns | Use `select('id', 'name', 'email')` |
| 50+ queries on one page | Multiple N+1 problems | Audit all relationship access in the view |
| Same query repeated identically | Caching opportunity | Use `Cache::remember()` (Day 41) |

A well-optimised page in FreelanceFlow should show 3–8 queries for most views. If you see more than 15, there is almost certainly an N+1 hiding somewhere.

---

## What We Learned Today

- **N+1 problem** — 1 query to load records, then N queries to load their relationships. Silent, deadly, grows with data
- **Laravel Debugbar** — `composer require barryvdh/laravel-debugbar --dev`. Shows all queries, their time, and their count per page
- **`with()`** — eager load relationships on the initial query. Generates an `IN` clause instead of a loop
- **`load()`** — eager load after the model is already in memory. Right choice with route model binding
- **Dot notation** — `with('projects.tags')` loads nested relationships in one call
- **`withCount()`** — adds a COUNT subquery. Result available as `$model->relationship_count`
- **`Model::preventLazyLoading(!app()->isProduction())`** — turns lazy loading into an exception in development. Catches every N+1 during development
- **`->count()` vs `->all()->count()`** — always use the SQL COUNT, never load all records just to count them

---

## Day 19 — File Uploads with Storage

Tomorrow we add file attachments to projects. Clients often share briefs, contracts, and assets — FreelanceFlow should store them. We will cover Laravel's Storage facade, local and cloud disk configuration, uploading files from a Livewire component using `WithFileUploads`, generating secure download URLs, and displaying uploaded files on the project detail page.

See you on Day 19.