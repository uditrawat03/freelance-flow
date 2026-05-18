# Day 31 — Multi-Tenancy Basics — FreelanceFlow for Teams

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"A solo freelancer tool is useful. A tool that a small agency team can share — with shared clients, projects, and invoices but separate logins — is a product people pay for. Today we add the concept of a workspace to FreelanceFlow. Multiple users, one shared dataset, clean data isolation between workspaces."*

---

## What Is Multi-Tenancy?

Multi-tenancy means one application serving multiple independent groups of users, with each group's data isolated from others. In FreelanceFlow terms: a freelancer and their VA share one workspace. A different agency has a completely separate workspace. Neither can see the other's clients.

There are three main approaches:

| Approach | How | Best for |
|---|---|---|
| **Separate databases** | Each tenant gets their own DB | Maximum isolation, expensive |
| **Separate schemas** | Each tenant gets their own schema (PostgreSQL) | Good isolation, complex setup |
| **Shared database, tenant column** | One DB, `workspace_id` on every table | Simplest, fine for most SaaS |

FreelanceFlow uses the shared database approach — a `workspace_id` column on every model and a global scope that filters automatically. This is how most Laravel SaaS products are built.

---

## What We Are Building Today

1. A **workspaces table** and **Workspace model**
2. A **workspace_user pivot** — many users per workspace, many workspaces per user
3. **`workspace_id` columns** on clients, projects, invoices
4. A **`BelongsToWorkspace` global scope** — replaces the `OwnedByUser` scope from Day 29
5. **Current workspace stored in the session** — switchable without logout
6. A **workspace switcher** in the navbar
7. **Auto-assign workspace** on model creation

---

## Step 1 — Workspaces Table

```bash
php artisan make:migration create_workspaces_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('plan')->default('free'); // free | pro | agency
            $table->json('settings')->nullable();    // workspace-level config
            $table->timestamps();
        });

        // Pivot: many users belong to many workspaces
        Schema::create('user_workspace', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner | admin | member
            $table->timestamps();
            $table->primary(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_workspace');
        Schema::dropIfExists('workspaces');
    }
};
```

---

## Step 2 — Add workspace_id to Data Tables

```bash
php artisan make:migration add_workspace_id_to_clients_table
php artisan make:migration add_workspace_id_to_projects_table
php artisan make:migration add_workspace_id_to_invoices_table
```

Each migration follows the same pattern:

```php
public function up(): void
{
    Schema::table('clients', function (Blueprint $table) {
        $table->foreignId('workspace_id')
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

---

## Step 3 — Workspace Model

```bash
php artisan make:model Workspace
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'owner_id', 'plan', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    // --- Relationships ---

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // --- Helpers ---

    public function hasUser(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    public function userRole(User $user): ?string
    {
        return $this->users()
                    ->where('user_id', $user->id)
                    ->value('workspace_user.role');
    }

    // Auto-generate slug from name
    public static function generateSlug(string $name): string
    {
        return str($name)->slug()->toString();
    }
}
```

---

## Step 4 — Add Workspace Relationships to User

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function workspaces(): BelongsToMany
{
    return $this->belongsToMany(Workspace::class)
                ->withPivot('role')
                ->withTimestamps();
}

public function ownedWorkspaces(): HasMany
{
    return $this->hasMany(Workspace::class, 'owner_id');
}

// Get the currently active workspace from the session
public function currentWorkspace(): ?Workspace
{
    $workspaceId = session('current_workspace_id');

    if ($workspaceId) {
        return $this->workspaces()->find($workspaceId);
    }

    // Default to the first workspace the user belongs to
    return $this->workspaces()->first();
}

// Switch the active workspace
public function switchWorkspace(Workspace $workspace): void
{
    if ($this->hasWorkspaceAccess($workspace)) {
        session(['current_workspace_id' => $workspace->id]);
    }
}

public function hasWorkspaceAccess(Workspace $workspace): bool
{
    return $this->workspaces()->where('workspace_id', $workspace->id)->exists();
}
```

---

## Step 5 — BelongsToWorkspace Global Scope

Replace the `OwnedByUser` scope from Day 29 with a workspace-aware scope:

```bash
php artisan make:scope BelongsToWorkspace
```

```php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToWorkspace implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $workspace = auth()->user()->currentWorkspace();

        if ($workspace) {
            $builder->where(
                $model->getTable() . '.workspace_id',
                $workspace->id
            );
        }
    }
}
```

Update the `Client`, `Project`, and `Invoice` models to use the new scope:

```php
// Replace OwnedByUser with BelongsToWorkspace in each model
use App\Models\Scopes\BelongsToWorkspace;

protected static function booted(): void
{
    static::addGlobalScope(new BelongsToWorkspace);

    // Auto-assign workspace_id on creation
    static::creating(function (self $model) {
        if (auth()->check() && ! $model->workspace_id) {
            $workspace = auth()->user()->currentWorkspace();
            $model->workspace_id = $workspace?->id;
        }
    });
}
```

Now `Client::all()` returns only clients in the current workspace. Switch the workspace in the session and every query instantly reflects the new workspace — no code changes needed anywhere.

---

## Step 6 — Workspace Switcher in the Navbar

Add a workspace switcher to `resources/views/partials/navbar.blade.php`:

```bash
php artisan make:livewire WorkspaceSwitcher --class
```

```php
<?php

namespace App\Livewire;

use App\Models\Workspace;
use Livewire\Component;

class WorkspaceSwitcher extends Component
{
    public bool $open = false;

    public function switch(int $workspaceId): void
    {
        $workspace = Workspace::findOrFail($workspaceId);

        // Security: only switch if user has access
        abort_unless(auth()->user()->hasWorkspaceAccess($workspace), 403);

        auth()->user()->switchWorkspace($workspace);

        // Redirect to dashboard to reload with new workspace context
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        $currentWorkspace = auth()->user()->currentWorkspace();
        $workspaces       = auth()->user()->workspaces()->get();

        return view('livewire.workspace-switcher', compact('currentWorkspace', 'workspaces'));
    }
}
```

Create `resources/views/livewire/workspace-switcher.blade.php`:

```blade
<div class="relative">
    <button
        wire:click="$toggle('open')"
        class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 font-medium"
    >
        <span class="w-6 h-6 rounded-md bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
            {{ substr($currentWorkspace?->name ?? 'W', 0, 1) }}
        </span>
        <span class="max-w-28 truncate">{{ $currentWorkspace?->name ?? 'No workspace' }}</span>
        <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </button>

    @if ($open)
        <div
            class="absolute left-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-200 z-50 py-1"
            wire:click.outside="$set('open', false)"
        >
            <p class="px-3 py-1.5 text-xs font-medium text-gray-400 uppercase tracking-wide">
                Your workspaces
            </p>

            @foreach ($workspaces as $workspace)
                <button
                    wire:click="switch({{ $workspace->id }})"
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700
                           hover:bg-gray-50 transition-colors text-left
                           {{ $currentWorkspace?->id === $workspace->id ? 'bg-indigo-50 text-indigo-700 font-medium' : '' }}"
                >
                    <span class="w-6 h-6 rounded-md bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700 shrink-0">
                        {{ substr($workspace->name, 0, 1) }}
                    </span>
                    <span class="truncate">{{ $workspace->name }}</span>
                    @if ($currentWorkspace?->id === $workspace->id)
                        <svg class="w-3.5 h-3.5 ml-auto shrink-0 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                </button>
            @endforeach

            <div class="border-t border-gray-100 mt-1 pt-1">
                <a href="{{ route('workspaces.create') }}"
                   class="flex items-center gap-2 px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 transition-colors">
                    <span class="w-6 h-6 rounded-md border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 text-base leading-none">+</span>
                    New workspace
                </a>
            </div>
        </div>
    @endif
</div>
```

Add the switcher to the navbar inside the `@auth` block:

```blade
@auth
    <livewire:workspace-switcher />
    <livewire:notification-bell />
    {{-- ... rest of navbar --}}
@endauth
```

---

## Step 7 — Workspace Creation Flow

```bash
php artisan make:livewire Workspaces/Create --class
```

```php
<?php

namespace App\Livewire\Workspaces;

use App\Models\Workspace;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Workspace — FreelanceFlow')]
class Create extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';

    public function save(): void
    {
        $this->validate();

        $workspace = Workspace::create([
            'name'     => $this->name,
            'slug'     => Workspace::generateSlug($this->name),
            'owner_id' => auth()->id(),
        ]);

        // Add the creator as owner in the pivot table
        $workspace->users()->attach(auth()->id(), ['role' => 'owner']);

        // Switch to the new workspace immediately
        auth()->user()->switchWorkspace($workspace);

        session()->flash('success', "Workspace \"{$workspace->name}\" created.");

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.workspaces.create');
    }
}
```

Add the route:

```php
// routes/web.php
use App\Livewire\Workspaces\Create as CreateWorkspace;

Route::get('/workspaces/create', CreateWorkspace::class)
     ->name('workspaces.create')
     ->middleware('auth');
```

---

## Step 8 — Update the Seeder

```php
// database/seeders/DatabaseSeeder.php
use App\Models\Workspace;

public function run(): void
{
    $user = User::factory()->create([
        'name'     => 'Demo User',
        'email'    => 'demo@freelanceflow.test',
        'password' => bcrypt('password'),
    ]);

    // Create a default workspace for the demo user
    $workspace = Workspace::create([
        'name'     => 'Demo Agency',
        'slug'     => 'demo-agency',
        'owner_id' => $user->id,
        'plan'     => 'pro',
    ]);

    // Attach user as owner
    $workspace->users()->attach($user->id, ['role' => 'owner']);

    // Store as current workspace in a fake session (for seeder context)
    // The global scope won't apply during seeding so we pass workspace_id directly
    $activeClients = Client::factory()->count(30)->active()->create([
        'workspace_id' => $workspace->id,
    ]);

    // ... rest of seeder
}
```

---

## Multi-Tenancy Middleware

Add middleware to ensure every authenticated request has an active workspace:

```bash
php artisan make:middleware EnsureWorkspaceSelected
```

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            // If user has no workspace at all, send them to create one
            if ($user->workspaces()->doesntExist()) {
                if (! $request->routeIs('workspaces.create')) {
                    return redirect()->route('workspaces.create')
                        ->with('info', 'Create a workspace to get started.');
                }
            }

            // If current_workspace_id is not set or invalid, set the first one
            $currentWorkspaceId = session('current_workspace_id');
            if (! $currentWorkspaceId || ! $user->hasWorkspaceAccess(
                \App\Models\Workspace::find($currentWorkspaceId) ?? new \App\Models\Workspace
            )) {
                $firstWorkspace = $user->workspaces()->first();
                if ($firstWorkspace) {
                    session(['current_workspace_id' => $firstWorkspace->id]);
                }
            }
        }

        return $next($request);
    }
}
```

Register it on all auth routes in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \App\Http\Middleware\EnsureWorkspaceSelected::class,
    ]);
})
```

---

## The Complete Multi-Tenancy Flow

```
User logs in
      ↓
EnsureWorkspaceSelected middleware runs
      ↓
Sets session('current_workspace_id') if not already set
      ↓
User visits /clients
      ↓
BelongsToWorkspace global scope fires:
WHERE clients.workspace_id = {current_workspace_id}
      ↓
Only this workspace's clients are returned
      ↓
User switches workspace via WorkspaceSwitcher
      ↓
session('current_workspace_id') updates
      ↓
Next query: WHERE clients.workspace_id = {new_workspace_id}
      ↓
Different workspace's data — seamlessly
```

---

## What We Learned Today

- **Shared database multi-tenancy** — one database, `workspace_id` on every table, global scope enforces isolation. The simplest and most common approach for Laravel SaaS
- **`BelongsToWorkspace` global scope** — automatically applies `WHERE workspace_id = ?` to every Eloquent query. Replacing it from Day 29's `OwnedByUser` means the same isolation now applies at workspace level instead of user level
- **`static::creating()` hook** — auto-assigns `workspace_id` on creation so no controller or Livewire component ever needs to set it manually
- **Session-based workspace context** — `session('current_workspace_id')` stores the active workspace. The global scope reads it on every request. Switching workspaces updates the session and every subsequent query reflects the new context
- **`EnsureWorkspaceSelected` middleware** — runs on every authenticated request. Ensures the session always has a valid workspace ID. Redirects new users who have no workspace to the creation flow
- **`workspace_user` pivot with `role` column** — one user can belong to many workspaces with different roles in each. `withPivot('role')` makes the role accessible via `$workspace->users->first()->pivot->role`
- **Workspace switcher as a Livewire component** — the `$toggle('open')` magic action toggles a boolean property. The dropdown closes via `wire:click.outside`

---

## Day 32 — Service Classes & Dependency Injection

Tomorrow we refactor FreelanceFlow's business logic. Controllers and Livewire components are starting to accumulate logic that does not belong to them — calculations, external API calls, data transformations. We will extract that logic into dedicated Service classes, bind them in the service container, and inject them via constructor and method injection — making every component thinner, every piece of logic testable in isolation, and the codebase easier to reason about as it scales.

See you on Day 32.