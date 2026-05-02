# Day 08 — CRUD: Update & Delete — Edit Clients with Livewire + Flux UI

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 17 min · **Level:** Beginner to Intermediate

---

> *"Day 07 gave FreelanceFlow the ability to add clients. Today we complete the CRUD loop — edit existing records and delete them safely with a confirmation modal. By the end of this post, FreelanceFlow has full client management."*

---

## Where We Are

At the end of Day 07, FreelanceFlow can:

- Display all clients at `/clients` with status badges
- Add a new client via a Livewire form with real-time validation
- Flash a success message after saving

What is missing: **editing existing clients** and **deleting them**. Today we build both — the Edit Livewire component with pre-filled data, a delete action, and a Flux confirmation modal so nothing gets accidentally removed.

---

## What We Are Building Today

1. An **Edit Client** Livewire component — full-page form pre-filled with existing data
2. An **update action** that saves changes and redirects
3. A **delete action** with a Flux modal confirmation
4. **Edit and Delete buttons** on each client card in the list
5. **Soft deletes** — records move to trash instead of being permanently wiped

---

## Step 1 — Add Soft Deletes to the Clients Table

Soft deletes add a `deleted_at` column to the table. When you "delete" a record, Laravel sets that column to the current timestamp instead of removing the row. The record disappears from all queries but stays in the database — recoverable, auditable, safe.

Create a new migration:

```bash
php artisan make:migration add_soft_deletes_to_clients_table
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
        Schema::table('clients', function (Blueprint $table) {
            $table->softDeletes(); // adds deleted_at timestamp column
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
```

Run it:

```bash
php artisan migrate
```

Now tell the `Client` model to use soft deletes:

```php
// app/Models/Client.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

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

From now on, `$client->delete()` sets `deleted_at` — it does not destroy the row. `Client::all()` and `Client::latest()->get()` automatically exclude soft-deleted records. Nothing else needs to change in the rest of the app.

---

## Step 2 — Create the Edit Livewire Component

Generate the component:

```bash
php artisan make:livewire Clients/Edit --class
```

This creates:

```
app/Livewire/Clients/Edit.php
resources/views/livewire/clients/edit.blade.php
```

Open `app/Livewire/Clients/Edit.php`:

```php
<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Client — FreelanceFlow')]
class Edit extends Component
{
    public Client $client;

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email|max:255')]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $phone = '';

    #[Rule('nullable|string|max:255')]
    public string $company = '';

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule('required|in:active,inactive,lead')]
    public string $status = 'active';

    // Tracks whether the delete confirmation modal is open
    public bool $confirmingDelete = false;

    public function mount(Client $client): void
    {
        // Route model binding passes the Client instance automatically
        // We fill the component properties from the model
        $this->client  = $client;
        $this->name    = $client->name;
        $this->email   = $client->email;
        $this->phone   = $client->phone ?? '';
        $this->company = $client->company ?? '';
        $this->notes   = $client->notes ?? '';
        $this->status  = $client->status;
    }

    public function update(): void
    {
        // Validate with unique rule that ignores the current client's own email
        $this->validate([
            'email' => "required|email|max:255|unique:clients,email,{$this->client->id}",
        ]);

        $this->client->update([
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'company' => $this->company,
            'notes'   => $this->notes,
            'status'  => $this->status,
        ]);

        session()->flash('success', 'Client updated successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $this->client->delete(); // soft delete

        session()->flash('success', 'Client removed successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.clients.edit');
    }
}
```

**Key things to understand:**

- `public Client $client` — Livewire supports route model binding. When the route has `{client}`, Livewire resolves the `Client` model automatically and passes it to `mount()`
- `mount()` — runs once when the component loads. We use it to pre-fill the form properties from the existing model data
- The `update()` method uses an inline `validate()` call with a custom email rule. The `unique:clients,email,{$this->client->id}` part tells Laravel to ignore the current client's own email when checking for uniqueness — without this, editing a client without changing their email would fail validation
- `$confirmingDelete` — a boolean property that controls whether the delete confirmation modal is visible
- `delete()` calls `$this->client->delete()` which triggers the soft delete

---

## Step 3 — Build the Edit Form View

Open `resources/views/livewire/clients/edit.blade.php`:

```blade
<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Edit Client</h1>
            <p class="mt-1 text-sm text-gray-500">Update {{ $client->name }}'s details.</p>
        </div>
        <a
            href="{{ route('clients.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700"
        >
            ← Back to clients
        </a>
    </div>

    {{-- Edit form --}}
    <flux:card class="max-w-2xl p-6 space-y-5">

        <flux:field>
            <flux:label>Full name <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="name"
                type="text"
                placeholder="Acme Corp or John Doe"
            />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email address <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="email"
                type="email"
                placeholder="hello@acme.com"
            />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>Phone <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="phone"
                type="tel"
                placeholder="+91 98765 43210"
            />
            <flux:error name="phone" />
        </flux:field>

        <flux:field>
            <flux:label>Company <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="company"
                type="text"
                placeholder="Acme Inc."
            />
            <flux:error name="company" />
        </flux:field>

        <flux:field>
            <flux:label>Status <span class="text-red-500">*</span></flux:label>
            <flux:select wire:model="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="lead">Lead</option>
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        <flux:field>
            <flux:label>Notes <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:textarea
                wire:model="notes"
                placeholder="Any notes about this client..."
                rows="3"
            />
            <flux:error name="notes" />
        </flux:field>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2">
            <div class="flex items-center gap-3">
                <flux:button
                    wire:click="update"
                    wire:loading.attr="disabled"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="update">Save changes</span>
                    <span wire:loading wire:target="update">Saving...</span>
                </flux:button>

                <a
                    href="{{ route('clients.index') }}"
                    class="text-sm text-gray-500 hover:text-gray-700"
                >
                    Cancel
                </a>
            </div>

            {{-- Delete trigger --}}
            <flux:button
                wire:click="confirmDelete"
                variant="danger"
                size="sm"
            >
                Delete client
            </flux:button>
        </div>

    </flux:card>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="confirmingDelete" class="max-w-sm">
        <div class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold">Delete client?</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Are you sure you want to remove <strong>{{ $client->name }}</strong>?
                    This action can be undone by an administrator.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <flux:button
                    wire:click="delete"
                    wire:loading.attr="disabled"
                    variant="danger"
                    class="flex-1"
                >
                    <span wire:loading.remove wire:target="delete">Yes, delete</span>
                    <span wire:loading wire:target="delete">Deleting...</span>
                </flux:button>

                <flux:button
                    wire:click="$set('confirmingDelete', false)"
                    variant="ghost"
                    class="flex-1"
                >
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
```

**What to notice in the view:**

- `<flux:modal wire:model="confirmingDelete">` — the modal's open/closed state is bound directly to the `$confirmingDelete` property. When `confirmDelete()` sets it to `true`, the modal opens. When the cancel button sets it to `false` via `$set`, the modal closes
- `wire:click="$set('confirmingDelete', false)"` — `$set` is a built-in Livewire magic action. It sets a property directly from the template without needing a dedicated PHP method
- The Delete button is separated visually to the right of the form actions — a small UX detail that prevents accidental clicks

---

## Step 4 — Add the Route

Open `routes/web.php` and add the edit route alongside the create route from Day 07:

```php
// routes/web.php
use App\Http\Controllers\ClientController;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;

// Controller handles list and show
Route::resource('clients', ClientController::class)
    ->only(['index', 'show'])
    ->middleware('auth');

// Livewire handles create and edit forms
Route::get('/clients/create', CreateClient::class)
    ->name('clients.create')
    ->middleware('auth');

Route::get('/clients/{client}/edit', EditClient::class)
    ->name('clients.edit')
    ->middleware('auth');
```

The `{client}` segment in the edit route matches the `Client $client` property in the component. Laravel's route model binding resolves the model automatically — you never write `Client::findOrFail($id)` manually.

---

## Step 5 — Add Edit and Delete Buttons to the Client List

Update the client card in `resources/views/clients/index.blade.php` to include action buttons:

```blade
@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    {{-- Flash message --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Clients</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $clients->count() }} {{ Str::plural('client', $clients->count()) }}</p>
        </div>
        <a
            href="{{ route('clients.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors"
        >
            + Add client
        </a>
    </div>

    {{-- Client list --}}
    @forelse ($clients as $client)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-3 flex items-center justify-between">

            {{-- Client info --}}
            <div>
                <p class="font-medium text-gray-900">{{ $client->name }}</p>
                <p class="text-sm text-gray-500">{{ $client->email }}</p>
                @if ($client->company)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $client->company }}</p>
                @endif
            </div>

            {{-- Right side: badge + actions --}}
            <div class="flex items-center gap-4">

                {{-- Status badge --}}
                @php
                    $badgeClass = match($client->status) {
                        'active'   => 'bg-green-100 text-green-700',
                        'inactive' => 'bg-gray-100 text-gray-600',
                        'lead'     => 'bg-yellow-100 text-yellow-700',
                        default    => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $badgeClass }}">
                    {{ ucfirst($client->status) }}
                </span>

                {{-- Action buttons --}}
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('clients.edit', $client) }}"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        Edit
                    </a>
                </div>

            </div>

        </div>
    @empty
        <div class="text-center py-16">
            <p class="text-gray-400 text-sm">No clients yet.</p>
            <a href="{{ route('clients.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">
                Add your first client
            </a>
        </div>
    @endforelse

@endsection
```

The edit link passes the `$client` model directly to `route()` — Laravel extracts the ID automatically. The delete itself happens inside the Edit component via the modal, keeping all destructive actions behind a confirmation step.

---

## Step 6 — Test the Full CRUD Loop

Walk through everything:

**Add a client:**
Click **+ Add client** → fill the form → save → flash message confirms → client appears in the list.

**Edit a client:**
Click **Edit** on any client → form loads pre-filled with their data → change the name → save → redirected to list → success message shows → updated name is visible.

**Test the unique email validation on edit:**
Open a client for editing. Change the email to one that belongs to a different client. Click Save — validation error appears. Change it back to the original email — no error, because the unique rule ignores the current record.

**Delete a client:**
Click **Edit** on a client → click **Delete client** (bottom right) → the Flux modal appears → click **Yes, delete** → redirected to the list → client is gone → flash message confirms.

**Verify soft delete:**
Open Tinker and check the database:

```bash
php artisan tinker

# The deleted client is hidden from normal queries
App\Models\Client::all(); // does not include the deleted record

# But it is still in the database
App\Models\Client::withTrashed()->get(); // includes deleted records

# You can restore it
App\Models\Client::withTrashed()->find(1)->restore();
```

---

## Soft Delete Reference

```php
// Delete (soft) — sets deleted_at, hides from queries
$client->delete();

// Restore a soft-deleted record
$client->restore();

// Permanently delete — removes the row entirely
$client->forceDelete();

// Query including soft-deleted records
Client::withTrashed()->get();

// Query only soft-deleted records
Client::onlyTrashed()->get();
```

---

## The `$set` Magic Action

In the modal we used `wire:click="$set('confirmingDelete', false)"` instead of writing a dedicated PHP method. `$set` is a built-in Livewire magic action that sets any public property directly from the template:

```blade
{{-- These are equivalent --}}
wire:click="$set('confirmingDelete', false)"

{{-- Same as writing this method in PHP: --}}
public function closeModal(): void
{
    $this->confirmingDelete = false;
}
```

Use `$set` for simple property toggles. Write a dedicated method when the action involves business logic, database calls, or multiple property changes.

---

## Route Model Binding — How It Works

When you define a route with `{client}`:

```php
Route::get('/clients/{client}/edit', EditClient::class);
```

And the Livewire component declares:

```php
public Client $client;

public function mount(Client $client): void { ... }
```

Laravel automatically queries `Client::findOrFail($id)` using the ID from the URL and passes the result to `mount()`. If the record does not exist — 404. If the record is soft-deleted — also 404, because Eloquent excludes soft-deleted records from `findOrFail()` by default.

This is one of Laravel's most elegant features: no manual `Client::findOrFail($id)` in every method, no forgetting error handling, no boilerplate.

---

## What We Learned Today

- **Soft deletes** — `use SoftDeletes` on the model + `$table->softDeletes()` migration. Records hide from queries but stay in the database
- **Route model binding** with Livewire — `public Client $client` resolved automatically from the URL
- **`mount()`** — pre-fills component properties from an existing Eloquent model
- **Unique validation ignoring the current record** — `unique:clients,email,{$this->client->id}`
- **Flux modals** — bound to a `bool` property via `wire:model`. Open and close with property state
- **`$set` magic action** — sets a property directly from the template without a PHP method
- **`withTrashed()`, `onlyTrashed()`, `restore()`** — working with soft-deleted records in Tinker and code

FreelanceFlow now has complete client management — create, read, update, and soft delete.

---

## Day 09 — Form Validation Deep Dive

Tomorrow we go deeper into validation. We will cover:

- Custom error messages in `#[Rule]` attributes
- Writing custom validation rules as a class
- Validating at the property level vs validating the whole form at once
- `$this->resetValidation()` — clearing errors programmatically
- Real-world validation patterns you will use throughout the rest of the series

See you on Day 09.