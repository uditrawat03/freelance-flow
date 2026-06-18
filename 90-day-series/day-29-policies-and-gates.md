# Day 29 — Policies & Gates — Authorization

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"Authentication asks who you are. Authorization asks what you are allowed to do. FreelanceFlow authenticates users on Day 6 — but right now any logged-in user can view, edit, or delete any client, project, or invoice in the database. Change the URL from /clients/1/edit to /clients/2/edit and you are editing someone else's client. Today we fix that with Laravel Policies and Gates."*

---

## Authentication vs Authorization

This distinction matters. Laravel handles both, but with different tools.

| | Authentication | Authorization |
|---|---|---|
| **Question** | Who are you? | What can you do? |
| **Tool** | Auth, Breeze, Sanctum | Policies, Gates |
| **Happens** | Login, session check | Every data access |
| **Failure** | 401 Unauthenticated | 403 Forbidden |

Authentication we covered on Day 6. Authorization is today.

---

## What We Are Building Today

1. **A `user_id` column on clients** — scope all data per user
2. **`ClientPolicy`** — who can view, create, update, delete a client
3. **`ProjectPolicy`** and **`InvoicePolicy`**
4. **Apply policies** in controllers and Livewire components
5. **`@can` in Blade** — conditional UI based on permissions
6. **Gates** — simple one-off permission checks
7. **Global query scopes** — ensure users only ever see their own data

---

## Step 1 — Scope Data Per User

Right now the `clients` table has no `user_id`. Every client is global. Fix that with a migration:

```bash
php artisan make:migration add_user_id_to_clients_table
```

```php
public function up(): void
{
    Schema::table('clients', function (Blueprint $table) {
        $table->foreignId('user_id')
              ->nullable()
              ->after('id')
              ->constrained()
              ->cascadeOnDelete();
    });
}
```

```bash
php artisan migrate
```

Do the same for projects and invoices:

```bash
php artisan make:migration add_user_id_to_projects_table
php artisan make:migration add_user_id_to_invoices_table
```

Each migration follows the same pattern — `user_id` foreign key with `cascadeOnDelete`.

Update `$fillable` on each model to include `user_id`:

```php
// Client, Project, Invoice models
protected $fillable = [
    'user_id',
    // ... existing fields
];
```

Update the seeder to assign all seeded data to the demo user:

```php
// database/seeders/DatabaseSeeder.php
$user = User::factory()->create([
    'name'     => 'Demo User',
    'email'    => 'demo@freelanceflow.test',
    'password' => bcrypt('password'),
]);

// Seed clients belonging to this user
$activeClients = Client::factory()->count(30)->active()->create([
    'user_id' => $user->id,
]);
```

---

## Step 2 — Global Query Scope on the Client Model

Instead of adding `where('user_id', auth()->id())` to every query, use a global scope. A global scope is applied automatically to every Eloquent query on the model.

```bash
php artisan make:scope OwnedByUser
```

Open `app/Models/Scopes/OwnedByUser.php`:

```php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnedByUser implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if a user is authenticated
        // Prevents issues in seeders and artisan commands
        if (auth()->check()) {
            $builder->where($model->getTable() . '.user_id', auth()->id());
        }
    }
}
```

Register the scope on the Client model:

```php
// app/Models/Client.php
use App\Models\Scopes\OwnedByUser;

protected static function booted(): void
{
    static::addGlobalScope(new OwnedByUser);

    // Auto-assign user_id on creation
    static::creating(function (Client $client) {
        if (auth()->check() && ! $client->user_id) {
            $client->user_id = auth()->id();
        }
    });
}
```

Add the same `booted()` method to the `Project` and `Invoice` models.

Now `Client::all()` automatically returns only the current user's clients. `Client::find(1)` returns `null` if client 1 belongs to a different user. No URL manipulation can expose another user's data.

> **Important:** In migrations, seeders, and artisan commands, `auth()->check()` returns false — so the global scope does not apply. Use `Client::withoutGlobalScope(OwnedByUser::class)->get()` in seeders to bypass the scope when needed.

---

## Step 3 — Create the ClientPolicy

```bash
php artisan make:policy ClientPolicy --model=Client
```

Open `app/Policies/ClientPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClientPolicy
{
    /**
     * Who can see the client list.
     * Any authenticated user can list their own clients.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Who can view a specific client.
     * Only the owner can view their client.
     */
    public function view(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }

    /**
     * Any authenticated user can create clients.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner can update a client.
     */
    public function update(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }

    /**
     * Only the owner can delete a client.
     */
    public function delete(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }

    /**
     * Restore a soft-deleted client.
     */
    public function restore(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }

    /**
     * Permanently delete.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        return $user->id === $client->user_id;
    }
}
```

Laravel automatically discovers policies by convention — `ClientPolicy` maps to the `Client` model. No registration needed in `AuthServiceProvider`.

---

## Step 4 — Create ProjectPolicy and InvoicePolicy

```bash
php artisan make:policy ProjectPolicy --model=Project
php artisan make:policy InvoicePolicy --model=Invoice
```

Both follow the same pattern — all methods check `$user->id === $model->user_id`. The only difference is `InvoicePolicy` adds a `pay` ability:

```php
// app/Policies/InvoicePolicy.php

public function view(User $user, Invoice $invoice): bool
{
    return $user->id === $invoice->user_id;
}

public function update(User $user, Invoice $invoice): bool
{
    return $user->id === $invoice->user_id;
}

public function delete(User $user, Invoice $invoice): bool
{
    return $user->id === $invoice->user_id;
}

// Custom ability — only the owner can send payment links
public function sendPaymentLink(User $user, Invoice $invoice): bool
{
    return $user->id === $invoice->user_id
        && in_array($invoice->status, ['sent', 'overdue']);
}
```

---

## Step 5 — Apply Policies in Controllers

Update the `ClientController` to use policy authorization:

```php
// app/Http/Controllers/ClientController.php
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ClientController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Client::class);

        // Global scope already filters by user — no extra where() needed
        return view('clients.index');
    }

    public function show(Client $client)
    {
        $this->authorize('view', $client);

        $client->load('projects.tags');
        return view('clients.show', compact('client'));
    }
}
```

When `$this->authorize('view', $client)` fails — the user does not own the client — Laravel automatically returns a 403 response. No manual `abort(403)` needed.

But wait — with the global scope applied, `Client::find($id)` already returns `null` for records the user does not own. That triggers a `ModelNotFoundException` → 404. The `$this->authorize()` call is a belt-and-suspenders check for cases where the scope might be bypassed (API endpoints, direct model lookups).

---

## Step 6 — Apply Policies in Livewire Components

Livewire components do not have `$this->authorize()` built in. Use the `Gate` facade or the helper function:

```php
// app/Livewire/Clients/Edit.php
use Illuminate\Support\Facades\Gate;

public function mount(Client $client): void
{
    // Abort with 403 if the user does not own this client
    Gate::authorize('update', $client);

    $this->client  = $client;
    $this->name    = $client->name;
    // ...
}

public function update(): void
{
    Gate::authorize('update', $this->client);

    // ... update logic
}

public function delete(): void
{
    Gate::authorize('delete', $this->client);

    $this->client->delete();
    // ...
}
```

`Gate::authorize()` throws an `AuthorizationException` (403) if the check fails — same behaviour as `$this->authorize()` in controllers.

---

## Step 7 — `@can` and `@cannot` in Blade

Use the `@can` directive to conditionally show UI elements based on authorization:

```blade
{{-- Only show Edit button if user can update this client --}}
@can('update', $client)
    <a href="{{ route('clients.edit', $client) }}" class="text-sm text-indigo-600">
        Edit
    </a>
@endcan

{{-- Only show Delete button if user can delete --}}
@can('delete', $client)
    <button wire:click="confirmDeleteAttachment({{ $client->id }})" class="text-sm text-red-500">
        Delete
    </button>
@endcan

{{-- Show something to users who CANNOT perform an action --}}
@cannot('delete', $client)
    <span class="text-xs text-gray-400">No permission</span>
@endcannot

{{-- Check a custom ability --}}
@can('sendPaymentLink', $invoice)
    <a href="{{ route('invoices.pay', $invoice) }}" class="btn-primary">
        Send payment link
    </a>
@endcan
```

---

## Step 8 — Gates for Simple Checks

Policies are for model-based authorization. Gates are for simple, application-level permissions that do not map to a specific model.

Define gates in `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Only allow users who have at least one paid invoice to access the analytics page
    Gate::define('access-analytics', function (User $user) {
        return $user->invoices()->paid()->exists();
    });

    // Only admin users can manage other users (for when we add teams)
    Gate::define('manage-users', function (User $user) {
        return $user->role === 'admin';
    });

    // Before hook — runs before all other authorization checks
    // Useful for super-admin bypass
    Gate::before(function (User $user, string $ability) {
        if ($user->is_super_admin) {
            return true; // bypass all checks
        }
    });
}
```

Use gates in controllers, Livewire, and Blade:

```php
// Controller
if (Gate::denies('access-analytics')) {
    abort(403, 'You need at least one paid invoice to access analytics.');
}

// Livewire
Gate::authorize('access-analytics');

// Blade
@can('access-analytics')
    <a href="/analytics">Analytics</a>
@endcan
```

---

## Step 9 — Update the Livewire ClientList Component

The global scope already filters clients by user. But add the policy check in `mount()` as defense-in-depth:

```php
// app/Livewire/ClientList.php
use Illuminate\Support\Facades\Gate;

public function mount(): void
{
    Gate::authorize('viewAny', Client::class);
}
```

And in the `render()` method, the query is already correct because the global scope applies automatically:

```php
public function render()
{
    $clients = Client::query()  // global scope: WHERE user_id = auth()->id()
        ->withCount('projects')
        ->when($this->search, ...)
        ->when($this->status, ...)
        ->latest()
        ->paginate(10);

    return view('livewire.client-list', compact('clients'));
}
```

---

## The Full Authorization Flow

Here is what happens when a user tries to access `/clients/99/edit` (a client that belongs to another user):

```
1. Laravel resolves the route
2. Route model binding: Client::find(99)
3. Global scope adds WHERE user_id = auth()->id()
4. Client 99 belongs to user 2, current user is user 1
5. Query returns null → ModelNotFoundException → 404
6. If somehow the model was resolved (e.g. scope was bypassed):
7. Gate::authorize('update', $client) fires
8. ClientPolicy::update() returns false (user_id mismatch)
9. AuthorizationException → 403
```

Two layers of protection. The global scope handles the common case (query returns null). The policy handles edge cases where the scope might be bypassed (API, admin tools, direct model lookups).

---

## Policy Response with Custom Messages

By default, a failed policy check returns a generic 403. Return a `Response` object for a custom message:

```php
use Illuminate\Auth\Access\Response;

public function update(User $user, Client $client): Response|bool
{
    if ($user->id !== $client->user_id) {
        return Response::deny('You do not own this client.');
    }

    return true;
}
```

In API context, this message appears in the 403 response body:

```json
{
  "message": "You do not own this client."
}
```

---

## Authorization Quick Reference

```php
// In controllers (with AuthorizesRequests trait)
$this->authorize('update', $client);
$this->authorize('create', Client::class);

// In Livewire and anywhere
Gate::authorize('update', $client);
Gate::allows('update', $client);   // returns bool
Gate::denies('update', $client);   // returns bool
Gate::check('update', $client);    // returns bool

// In Blade
@can('update', $client) ... @endcan
@cannot('update', $client) ... @endcannot
@canany(['update', 'delete'], $client) ... @endcanany

// Policy methods
public function viewAny(User $user): bool
public function view(User $user, Client $client): bool
public function create(User $user): bool
public function update(User $user, Client $client): bool
public function delete(User $user, Client $client): bool
public function restore(User $user, Client $client): bool
public function forceDelete(User $user, Client $client): bool

// Global scope bypass (for seeders, admin tools)
Client::withoutGlobalScope(OwnedByUser::class)->get();
Client::withoutGlobalScopes()->get(); // bypass ALL global scopes
```

---

## What We Learned Today

- **Authentication vs Authorization** — authentication confirms identity, authorization controls access
- **Global query scopes** — automatically add `WHERE user_id = auth()->id()` to every query. No developer can forget to add it
- **`static::creating()`** — auto-assigns `user_id` when a model is created. The controller and Livewire component never need to set it manually
- **`php artisan make:policy --model=Client`** — creates a policy with all CRUD methods pre-stubbed. Laravel auto-discovers it via naming convention
- **`$this->authorize()` in controllers** — throws `AuthorizationException` (403) on failure
- **`Gate::authorize()` in Livewire** — same behaviour. Use in `mount()` and in every action that touches the model
- **`@can` and `@cannot`** — Blade directives for conditional UI based on authorization
- **Gates** — simple application-level permissions without a model. Defined in `AppServiceProvider`
- **`Gate::before()`** — a hook that runs before all other checks. Use for super-admin bypass
- **Two-layer protection** — global scope returns null for unauthorized records (404), policy rejects unauthorized actions (403)

---

## Day 30 — Roles & Permissions with Spatie

Tomorrow we add a proper roles and permissions system using the Spatie Laravel Permission package. Users will have roles — admin, manager, freelancer — and each role has specific permissions. We will build a role assignment UI, update the middleware to check roles, and prepare FreelanceFlow for multi-user teams in Phase 3.

See you on Day 30.
