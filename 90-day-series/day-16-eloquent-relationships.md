# Day 16 — Eloquent Relationships — Clients Have Projects

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"A client without projects is just a contact. A project without a client is just a to-do item. The relationship between them is what makes FreelanceFlow useful. Today we wire it up — one client has many projects, one project belongs to one client — and we build the project management screens to go with it."*

---

## Welcome to Phase 2

Phase 1 gave FreelanceFlow its foundation — auth, CRUD, validation, search, components, and a solid Eloquent model. Every pattern we used in those 14 days becomes second nature in Phase 2 because we repeat and build on all of it.

Phase 2 is about features. The things that make FreelanceFlow a product rather than a demo. We start where every freelance business starts: projects.

---

## What We Are Building Today

1. The **projects table** — migration with the right columns and a foreign key to clients
2. The **Project model** with `$fillable`, scopes, and accessors
3. **Eloquent relationships** — `hasMany` on Client, `belongsTo` on Project
4. A **ProjectFactory** and seeder
5. A **Projects Livewire component** on the client detail page
6. A **Create Project** Livewire form

---

## Step 1 — The Projects Migration

Think about what a project needs before writing a single line. A freelancer tracking projects needs to know:

- Which client it belongs to
- What the project is called
- A description of the work
- Its current status — draft, active, on hold, completed, cancelled
- The budget or estimated value
- The deadline

Create the migration:

```bash
php artisan make:migration create_projects_table
```

Open the generated file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Foreign key — every project belongs to one client
            $table->foreignId('client_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('status')->default('draft');
            // Possible values: draft, active, on_hold, completed, cancelled

            $table->decimal('budget', 10, 2)->nullable();
            // decimal(10,2) stores up to 99,999,999.99

            $table->date('deadline')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropForeignKeys('projects');
        Schema::dropIfExists('projects');
    }
};
```

Run it:

```bash
php artisan migrate
```

**About `cascadeOnDelete()`:** When a client is deleted (soft or hard), their projects are also deleted automatically at the database level. This keeps data integrity even if application-level code misses something. Since we use soft deletes on clients, deleting a client soft-deletes the client record — but the cascade applies to actual hard deletes. We will handle soft delete cascading in the model.

---

## Step 2 — The Project Model

```bash
php artisan make:model Project
```

Open `app/Models/Project.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'status',
        'budget',
        'deadline',
    ];

    protected $casts = [
        'budget'     => 'decimal:2',
        'deadline'   => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // --- Relationship ---

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // --- Scopes ---

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotNull('deadline')
              ->where('deadline', '<', now())
              ->whereNotIn('status', ['completed', 'cancelled']);
    }

    // --- Accessors ---

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'draft'      => 'Draft',
                'active'     => 'Active',
                'on_hold'    => 'On Hold',
                'completed'  => 'Completed',
                'cancelled'  => 'Cancelled',
                default      => ucfirst($this->status),
            },
        );
    }

    protected function formattedBudget(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->budget
                ? '₹' . number_format($this->budget, 2)
                : 'No budget set',
        );
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->deadline
                && $this->deadline->isPast()
                && ! in_array($this->status, ['completed', 'cancelled']),
        );
    }

    protected function daysUntilDeadline(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->deadline
                ? now()->diffInDays($this->deadline, false)
                : null,
        );
    }
}
```

**What is new here:**

- `'budget' => 'decimal:2'` — casts the decimal column to a PHP float with 2 decimal places. No manual `number_format()` in queries
- `'deadline' => 'date'` — casts to a Carbon instance. Unlocks `->isPast()`, `->diffInDays()`, `->format()` directly on the property
- `scopeOverdue()` — a scope that combines multiple conditions. Projects past their deadline that are not completed or cancelled
- `$project->is_overdue` — a boolean accessor using the cast `deadline` Carbon instance
- `$project->days_until_deadline` — positive = days remaining, negative = days overdue

---

## Step 3 — Add the Relationship to the Client Model

Open `app/Models/Client.php` and add the `hasMany` relationship:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    // ... existing code ...

    // --- Relationships ---

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // Only active projects
    public function activeProjects(): HasMany
    {
        return $this->hasMany(Project::class)->active();
    }
}
```

Now you can access a client's projects anywhere in the app:

```php
$client->projects;                    // all projects (Collection)
$client->projects()->count();         // count without loading all
$client->projects()->active()->get(); // filtered projects
$client->activeProjects;              // shorthand via relationship method
```

---

## Step 4 — ProjectFactory and Seeder

```bash
php artisan make:factory ProjectFactory --model=Project
```

Open `database/factories/ProjectFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $status   = fake()->randomElement(['draft', 'draft', 'active', 'active', 'active', 'on_hold', 'completed', 'cancelled']);
        $deadline = in_array($status, ['active', 'on_hold'])
            ? fake()->dateTimeBetween('now', '+6 months')
            : (fake()->boolean(50) ? fake()->dateTimeBetween('-3 months', '+3 months') : null);

        return [
            'client_id'   => Client::factory(), // creates a new client if not specified
            'name'        => fake()->randomElement([
                'Website Redesign',
                'Mobile App Development',
                'Brand Identity',
                'SEO Campaign',
                'E-commerce Platform',
                'CRM Integration',
                'Social Media Strategy',
                'Annual Report Design',
                'API Development',
                'Marketing Dashboard',
            ]) . ' — ' . fake()->company(),
            'description' => fake()->boolean(70) ? fake()->paragraph() : null,
            'status'      => $status,
            'budget'      => fake()->boolean(80)
                ? fake()->randomFloat(2, 5000, 250000)
                : null,
            'deadline'    => $deadline,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'   => 'completed',
            'deadline' => fake()->dateTimeBetween('-6 months', '-1 week'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status'   => 'active',
            'deadline' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}
```

Update `database/seeders/DatabaseSeeder.php` to seed projects alongside clients:

```php
<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo user
        User::factory()->create([
            'name'     => 'Demo User',
            'email'    => 'demo@freelanceflow.test',
            'password' => bcrypt('password'),
        ]);

        // 50 clients
        $activeClients   = Client::factory()->count(30)->active()->create();
        $inactiveClients = Client::factory()->count(10)->inactive()->create();
        $leads           = Client::factory()->count(10)->lead()->create();

        // Seed projects for active clients only
        // Each active client gets 1–4 projects
        $activeClients->each(function (Client $client) {
            $count = fake()->numberBetween(1, 4);
            Project::factory()->count($count)->create(['client_id' => $client->id]);
        });

        // A few completed projects for some inactive clients
        $inactiveClients->take(5)->each(function (Client $client) {
            Project::factory()->count(2)->completed()->create(['client_id' => $client->id]);
        });

        $this->command->info('✓ Seeded 50 clients and ~90 projects');
    }
}
```

Run a fresh seed to verify:

```bash
php artisan migrate:fresh --seed

php artisan tinker
>>> Project::count()         // ~90
>>> Project::active()->count() // majority
>>> Client::first()->projects->count() // 1–4
```

---

## Step 5 — Client Show Page with Projects

We need a client detail page that shows all of their projects. First add the `show()` method to `ClientController`:

```php
// app/Http/Controllers/ClientController.php
public function show(Client $client)
{
    // Eager load projects to avoid N+1 queries
    $client->load('projects');

    return view('clients.show', compact('client'));
}
```

Create `resources/views/clients/show.blade.php`:

```blade
@extends('layouts.app')

@section('title', $client->display_name . ' — FreelanceFlow')

@section('content')

    <x-page-header
        :title="$client->display_name"
        :subtitle="$client->email"
    >
        <div class="flex items-center gap-3">
            <x-client-status :status="$client->status" />
            <a
                href="{{ route('clients.edit', $client) }}"
                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
            >
                Edit client
            </a>
        </div>
    </x-page-header>

    {{-- Client meta --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        @if ($client->company)
            <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
                <p class="text-xs text-gray-400">Company</p>
                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $client->company }}</p>
            </div>
        @endif
        @if ($client->phone)
            <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
                <p class="text-xs text-gray-400">Phone</p>
                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $client->phone }}</p>
            </div>
        @endif
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs text-gray-400">Projects</p>
            <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $client->projects->count() }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3">
            <p class="text-xs text-gray-400">Client since</p>
            <p class="text-sm font-medium text-gray-900 mt-0.5">
                {{ $client->created_at->format('M Y') }}
            </p>
        </div>
    </div>

    {{-- Projects section --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Projects</h2>
        <a
            href="{{ route('projects.create', ['client' => $client->id]) }}"
            class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-md transition-colors"
        >
            + New project
        </a>
    </div>

    @forelse ($client->projects()->latest()->get() as $project)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-2 flex items-center justify-between">
            <div>
                <p class="font-medium text-gray-900 text-sm">{{ $project->name }}</p>
                <div class="flex items-center gap-3 mt-1">
                    <x-project-status :status="$project->status" />
                    @if ($project->deadline)
                        <span class="text-xs {{ $project->is_overdue ? 'text-red-500 font-medium' : 'text-gray-400' }}">
                            {{ $project->is_overdue ? 'Overdue · ' : 'Due ' }}
                            {{ $project->deadline->format('M d, Y') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-4">
                @if ($project->budget)
                    <span class="text-sm text-gray-600 font-medium">{{ $project->formatted_budget }}</span>
                @endif
                <a href="{{ route('projects.edit', $project) }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Edit
                </a>
            </div>
        </div>
    @empty
        <x-empty-state
            message="No projects for this client yet."
            cta-text="Add first project"
            :cta-href="route('projects.create', ['client' => $client->id])"
        />
    @endforelse

@endsection
```

---

## Step 6 — Build the ProjectStatus Blade Component

We need a status badge for projects just like we have for clients. Create it:

```bash
php artisan make:component ProjectStatus
```

`app/View/Components/ProjectStatus.php`:

```php
<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProjectStatus extends Component
{
    public string $badgeClass;
    public string $label;

    public function __construct(public string $status)
    {
        [$this->badgeClass, $this->label] = match($status) {
            'draft'     => ['bg-gray-100 text-gray-600',   'Draft'],
            'active'    => ['bg-blue-100 text-blue-700',   'Active'],
            'on_hold'   => ['bg-yellow-100 text-yellow-700', 'On Hold'],
            'completed' => ['bg-green-100 text-green-700', 'Completed'],
            'cancelled' => ['bg-red-100 text-red-600',     'Cancelled'],
            default     => ['bg-gray-100 text-gray-600',   ucfirst($status)],
        };
    }

    public function render(): View|Closure|string
    {
        return view('components.project-status');
    }
}
```

`resources/views/components/project-status.blade.php`:

```blade
<span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $badgeClass }}">
    {{ $label }}
</span>
```

---

## Step 7 — Add the Project Routes

Update `routes/web.php`:

```php
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Clients
    Route::get('/clients/create', CreateClient::class)->name('clients.create');
    Route::get('/clients/{client}/edit', EditClient::class)->name('clients.edit');
    Route::resource('clients', ClientController::class)->only(['index', 'show']);

    // Projects — controller for show, Livewire for forms (Day 17)
    Route::resource('projects', ProjectController::class)->only(['show']);

    // Project create with optional client pre-selection via query string
    Route::get('/projects/create', \App\Livewire\Projects\Create::class)
        ->name('projects.create');

    Route::get('/projects/{project}/edit', \App\Livewire\Projects\Edit::class)
        ->name('projects.edit');
});
```

Create the `ProjectController` stub — just the show method for now:

```bash
php artisan make:controller ProjectController
```

```php
// app/Http/Controllers/ProjectController.php
use App\Models\Project;

class ProjectController extends Controller
{
    public function show(Project $project)
    {
        return view('projects.show', compact('project'));
    }
}
```

---

## Step 8 — Update the Client List to Link to Client Detail

Update the client card in `resources/views/livewire/client-list.blade.php` to make the client name a link:

```blade
{{-- Client name becomes a link to the detail page --}}
<a
    href="{{ route('clients.show', $client) }}"
    class="font-medium text-gray-900 text-sm hover:text-indigo-600 transition-colors"
>
    {{ $client->display_name }}
</a>
```

---

## How Eloquent Relationships Work Under the Hood

Understanding what Laravel actually does when you access `$client->projects` helps you avoid mistakes later.

```php
// This triggers a SELECT * FROM projects WHERE client_id = ?
$client->projects

// This returns the same data but also lets you chain additional constraints
$client->projects()  // returns a HasMany query builder

// These are different:
$client->projects        // magic property — runs the query, returns Collection
$client->projects()      // method call — returns Builder, query not yet run
$client->projects()->active()->get()  // chain on the builder, then run
```

The property syntax (`$client->projects`) is called **dynamic property access**. The method syntax (`$client->projects()`) returns the relationship query builder. Use the property when you want the result. Use the method when you want to add constraints before running.

---

## Eager Loading — Preventing N+1

If you load a list of clients and then access `$client->projects` for each one, you generate one query per client — the N+1 problem.

```php
// ❌ N+1 — 1 query for clients + 1 query per client for projects
$clients = Client::all();
foreach ($clients as $client) {
    echo $client->projects->count(); // hits the database for each client
}

// ✓ Eager loading — 2 queries total, regardless of client count
$clients = Client::with('projects')->get();
foreach ($clients as $client) {
    echo $client->projects->count(); // already loaded, no extra query
}
```

We will cover this in detail on Day 18. For now, use `$client->load('projects')` in show methods and `Client::with('projects')` in list queries whenever you know you will access the relationship.

---

## What We Learned Today

- **`hasMany` and `belongsTo`** — the two sides of a one-to-many relationship. Defined on the model, accessed as properties or method chains
- **`foreignId('client_id')->constrained()->cascadeOnDelete()`** — the clean Laravel way to define a foreign key with a cascade
- **`'deadline' => 'date'`** — casts a date column to Carbon. Unlocks `isPast()`, `diffInDays()`, `format()` as direct property access
- **`$client->projects` vs `$client->projects()`** — property returns a Collection, method returns a Builder you can add constraints to
- **Eager loading preview** — `Client::with('projects')` and `$client->load('projects')` prevent N+1 queries
- **Seeding with relationships** — `$activeClients->each(fn($client) => Project::factory()->count(...)→create(['client_id' => $client->id]))`
- **The `ProjectStatus` Blade component** — same pattern as `ClientStatus`, built in minutes because the pattern is familiar

---

## Day 17 — Many-to-Many Relationships

Tomorrow we add tags to projects. A project can have many tags. A tag can belong to many projects. That is a many-to-many relationship — it requires a pivot table and the `belongsToMany` method. We will also cover `withPivot()`, `attach()`, `detach()`, `sync()`, and build a tag selector on the project form.

See you on Day 17.