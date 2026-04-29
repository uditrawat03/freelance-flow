# Day 5 — Eloquent ORM basics — your database speaks PHP

> **Series:** Laravel Zero to Hero · **Phase 1 — Foundations** · May 01, 2026
> **Read time:** 12 min · **Level:** Beginner

---

> *"On Day 4 we built the clients table. Today we stop talking to that table in raw SQL and start talking to it in pure PHP. Eloquent ORM turns every database row into a PHP object — and querying your database becomes as natural as calling a method."*

---

## Recap & Context

We have a `clients` table with eight columns. We have a `ClientController` returning a hardcoded array. Today we connect the two. We'll create a `Client` model, understand `$fillable` and mass assignment protection, and replace the fake data with a real `Client::all()` database call. FreelanceFlow starts talking to a real database for the first time.

---

## What is Eloquent?

Eloquent is Laravel's ORM — Object Relational Mapper. It maps each database table to a PHP class called a Model. Every row in the `clients` table becomes an instance of the `Client` model. Every column becomes a property on that object.

Instead of writing `SELECT * FROM clients WHERE id = 1`, you write `Client::find(1)`. Instead of `INSERT INTO clients ...`, you write `Client::create([...])`. The SQL still runs — Eloquent just writes it for you.

| Concept | What it means |
|---|---|
| Convention over config | A model named `Client` maps to `clients` automatically. Laravel pluralises the class name. |
| ActiveRecord pattern | Each database row becomes a model instance. Columns are properties. Methods are queries. |
| Fluent query builder | Chain `where()`, `orderBy()`, `limit()` to build queries naturally in PHP. |

---

## Step 1 — Creating the Client Model

One Artisan command creates the model file. The `-m` flag would also create a migration — but we already have ours, so we skip it:

```bash
# Create just the model
php artisan make:model Client

# For future reference — model + migration in one shot
php artisan make:model Client -m

# Model + migration + controller + factory + seeder
php artisan make:model Invoice -mcfs
```

Open [`app/Models/Client.php`](../app/Models/Client.php). Laravel created a class that extends `Model` — and that's almost everything you need. By convention, the `Client` model maps to the `clients` table. No configuration required.

> **The naming convention:** `Client` → `clients`, `Project` → `projects`, `Invoice` → `invoices`. Laravel pluralises the class name automatically. You never have to specify the table name unless you're doing something non-standard.

---

## Step 2 — Mass Assignment & $fillable

Before we can create records using `Client::create([...])`, we need to tell Eloquent which columns are safe to fill from user input. This is called **mass assignment protection** — a security feature that prevents attackers from injecting unexpected fields (like `is_admin = true`) into your database.

The `$fillable` array is your allowlist. Only columns listed here can be set via `create()` or `fill()`:

```php
// app/Models/Client.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'notes',
        'status',
    ];
}
```

> **Warning:** If you try `Client::create([...])` without `$fillable`, Laravel throws a `MassAssignmentException`. This is intentional — it forces you to be explicit about what your model accepts. Never set `$guarded = []` to bypass this on a production app.

---

## Step 3 — Replacing the Hardcoded Array

Open [`ClientController.php`](../app/Http/Controllers/ClientController.php) and update the `index()` method. Replace the hardcoded array with a single Eloquent call:

```php
// app/Http/Controllers/ClientController.php
use App\Models\Client;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::all();  // SELECT * FROM clients

        return view('clients.index', compact('clients'));
    }
}
```

---

## Step 4 — Updating the Blade View

Since `Client::all()` returns model objects, update [`clients/index.blade.php`](../resources/views/clients/index.blade.php) to use arrow notation instead of bracket notation:

```blade
{{-- Before — hardcoded array --}}
{{ $client['name'] }}
{{ $client['email'] }}

{{-- After — Eloquent model object --}}
{{ $client->name }}
{{ $client->email }}
```

Here is the full updated view:

```blade
{{-- resources/views/clients/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-semibold">Clients</h1>
        <a href="{{ route('clients.create') }}" class="rounded-lg px-4 py-1 bg-blue-500 hover:bg-blue-600 text-white">
            Add client
        </a>
    </div>

    @forelse ($clients as $client)
        <div
            class="client-card flex items-center justify-between gap-4 p-4 mb-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition">
            <div class="flex flex-col">
                <strong class="text-lg font-semibold text-gray-900">
                    {{ $client->name }}
                </strong>

                <span class="text-sm text-gray-500">
                    {{ $client->email }}
                </span>
            </div>

            @php
                $statusClasses = match ($client->status) {
                    'active' => 'bg-green-100 text-green-700',
                    'inactive' => 'bg-red-100 text-red-700',
                    default => 'bg-gray-100 text-gray-700',
                };
            @endphp

            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $statusClasses }}">
                {{ ucfirst($client->status) }}
            </span>
        </div>


    @empty
        <p>No clients yet.
            <a href="{{ route('clients.create') }}">Add your first one.</a>
        </p>
    @endforelse
@endsection
```

---

## Tinker — Test Without a Browser

Tinker is Laravel's REPL — a live PHP shell with full access to your application. It's the fastest way to test Eloquent queries before wiring them to a controller:

```bash
# Open the REPL
php artisan tinker

use App\Models\Client;

# Create test clients directly in the database
Client::create(['name' => 'Acme Corp', 'email' => 'hello@acme.com', 'status' => 'active']);
Client::create(['name' => 'Stark Industries', 'email' => 'tony@stark.com', 'status' => 'active']);
Client::create(['name' => 'Wayne Enterprises', 'email' => 'bruce@wayne.com', 'status' => 'lead']);

# Fetch all clients
Client::all();

# Find one by ID
Client::find(1);

# Trigger a 404 with a missing ID
Client::findOrFail(999);

# Count all clients
Client::count();
```

---

## Eloquent Method Reference

```php
// Read
Client::all();                                    // all rows as a collection
Client::find(1);                                  // by PK, returns null if missing
Client::findOrFail(1);                            // by PK, throws 404 if missing
Client::first();                                  // first record
Client::count();                                  // total row count
Client::where('status', 'active')->get();         // filtered collection
Client::where('status', 'active')->first();       // first match only
Client::orderBy('name')->get();                   // sorted ascending
Client::latest()->get();                          // ordered by created_at desc

// Create
Client::create(['name' => 'Acme', 'email' => 'hi@acme.com', 'status' => 'active']);

// Update
$client = Client::findOrFail(1);
$client->update(['status' => 'inactive']);        // update specific fields
$client->name = 'New Name';
$client->save();                                  // save dirty attributes

// Delete
$client->delete();                                // delete this instance
Client::destroy(1);                               // delete by ID
Client::destroy([1, 2, 3]);                       // delete multiple IDs
```

---

## What's Next

FreelanceFlow is now reading from a real database. Tomorrow on **Day 6** we add authentication with Laravel livewire — login, register, and password reset — scaffolded in one command. We'll then protect the clients route so only logged-in users can access it.