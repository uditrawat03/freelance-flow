# Day 07 — CRUD: Create & Read — Add Clients with Livewire + Flux UI

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 16 min · **Level:** Beginner to Intermediate

---

> *"Yesterday we locked FreelanceFlow behind a login wall. Today we give it real functionality. By the end of this post, you can add a new client through a form, watch validation errors appear in real time, and see the saved record appear in the list — all without a single page reload."*

---

## Where We Are

At the end of Day 06, FreelanceFlow has:

- A working login and register page built manually with Livewire + Flux UI
- Route protection via `->middleware('auth')`
- A client list at `/clients` pulling real data from the database

What it cannot do yet: **add new clients**. The create form does not exist. Today we build it — and in doing so, we learn the core Livewire pattern you will repeat for every form in this series.

---

## What We Are Building Today

By the end of this post you will have:

1. A **Create Client** Livewire component with a Flux UI form
2. **Real-time validation** — errors appear as you type, before you submit
3. A **store action** that saves to the database and redirects
4. A **flash success message** on the client list after saving
5. An **Add Client button** on the client list page that links to the form

---

## The Livewire CRUD Pattern

Before writing code, understand the pattern we follow for every form in FreelanceFlow:

```
User fills in the form
       ↓
wire:model syncs input to Livewire property in real time
       ↓
User clicks submit → wire:click fires a Livewire action
       ↓
Action validates with #[Rule] attributes
       ↓
If validation fails → flux:error components show errors automatically
       ↓
If validation passes → Eloquent saves the record
       ↓
Redirect to list with flash message
```

This is the loop. Every create, edit, and delete in FreelanceFlow follows it.

---

## Step 1 — Create the Livewire Component

Generate the Livewire component for creating a client:

```bash
php artisan make:livewire Clients/Create --class
```

This creates two files:

```
app/Livewire/Clients/Create.php
resources/views/livewire/clients/create.blade.php
```

---

## Step 2 — Build the Component Class

Open `app/Livewire/Clients/Create.php` and write the full component:

```php
<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Add Client — FreelanceFlow')]
class Create extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email|max:255|unique:clients,email')]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $phone = '';

    #[Rule('nullable|string|max:255')]
    public string $company = '';

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule('required|in:active,inactive,lead')]
    public string $status = 'active';

    public function save(): void
    {
        $this->validate();

        Client::create([
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'company' => $this->company,
            'notes'   => $this->notes,
            'status'  => $this->status,
        ]);

        session()->flash('success', 'Client added successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.clients.create');
    }
}
```

**What each part does:**

- `#[Layout('layouts.app')]` — uses our main app layout with navbar and sidebar
- `#[Title(...)]` — sets the browser tab title
- `#[Rule(...)]` — declares validation rules directly on each property. No separate `rules()` method needed
- `unique:clients,email` — checks that no other client has the same email in the database
- `session()->flash('success', ...)` — stores a one-time message that survives a redirect
- `$this->redirect(route('clients.index'), navigate: true)` — redirects after saving. `navigate: true` uses Livewire's SPA-style navigation

---

## Step 3 — Build the Form View

Open `resources/views/livewire/clients/create.blade.php` and build the form using Flux UI components:

```blade
<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Add Client</h1>
            <p class="mt-1 text-sm text-gray-500">Fill in the details to add a new client to FreelanceFlow.</p>
        </div>
        <a
            href="{{ route('clients.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700"
        >
            ← Back to clients
        </a>
    </div>

    {{-- Form card --}}
    <flux:card class="max-w-2xl p-6 space-y-5">

        {{-- Name --}}
        <flux:field>
            <flux:label>Full name <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="name"
                type="text"
                placeholder="Acme Corp or John Doe"
                autofocus
            />
            <flux:error name="name" />
        </flux:field>

        {{-- Email --}}
        <flux:field>
            <flux:label>Email address <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="email"
                type="email"
                placeholder="hello@acme.com"
            />
            <flux:error name="email" />
        </flux:field>

        {{-- Phone --}}
        <flux:field>
            <flux:label>Phone <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="phone"
                type="tel"
                placeholder="+91 98765 43210"
            />
            <flux:error name="phone" />
        </flux:field>

        {{-- Company --}}
        <flux:field>
            <flux:label>Company <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="company"
                type="text"
                placeholder="Acme Inc."
            />
            <flux:error name="company" />
        </flux:field>

        {{-- Status --}}
        <flux:field>
            <flux:label>Status <span class="text-red-500">*</span></flux:label>
            <flux:select wire:model="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="lead">Lead</option>
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        {{-- Notes --}}
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
        <div class="flex items-center gap-3 pt-2">
            <flux:button
                wire:click="save"
                wire:loading.attr="disabled"
                variant="primary"
            >
                <span wire:loading.remove wire:target="save">Save client</span>
                <span wire:loading wire:target="save">Saving...</span>
            </flux:button>

            <a
                href="{{ route('clients.index') }}"
                class="text-sm text-gray-500 hover:text-gray-700"
            >
                Cancel
            </a>
        </div>

    </flux:card>
</div>
```

**Key things to notice:**

- `wire:model.live` on name and email — validation triggers as you type, not only on submit. Use this on critical fields where instant feedback matters
- `wire:model` (without `.live`) on phone, company, notes, status — validates on submit only, avoiding noisy errors on optional fields
- `wire:loading.attr="disabled"` — disables the button while the save action is running
- `wire:target="save"` — scopes the loading state to only the save action, not other possible wire interactions on the page
- `<flux:error name="name" />` — automatically reads validation errors from Livewire and displays them. No manual `@error` directives needed

---

## Step 4 — Add the Route

Open `routes/web.php`. The Livewire component is a full-page component, so it gets its own route. Add it alongside the resource route:

```php
// routes/web.php
use App\Http\Controllers\ClientController;
use App\Livewire\Clients\Create as CreateClient;

// Create client — Livewire full-page component
Route::get('/clients/create', CreateClient::class)
    ->name('clients.create')
    ->middleware('auth');

// Clients list and detail — still handled by the controller
Route::resource('clients', ClientController::class)
    ->only(['index', 'show'])
    ->middleware('auth');
```

> **Why split the routes?** The `index` and `show` methods stay on the controller because they just query and return data — no form handling needed. The `create` form is a Livewire component because it manages form state, real-time validation, and submission. We will move `edit` to Livewire on Day 08 for the same reason.

---

## Step 5 — Update the Client List View

Open `resources/views/clients/index.blade.php`. We need to:

1. Add the **Add Client** button
2. Display the **flash success message** after a save

```blade
@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    {{-- Flash success message --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Clients</h1>
            <p class="mt-1 text-sm text-gray-500">Manage all your clients in one place.</p>
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
            <div>
                <p class="font-medium text-gray-900">{{ $client->name }}</p>
                <p class="text-sm text-gray-500">{{ $client->email }}</p>
                @if ($client->company)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $client->company }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
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

---

## Step 6 — Update the ClientController

The `index` method on `ClientController` stays the same — it returns the client list. Just make sure it orders by latest:

```php
// app/Http/Controllers/ClientController.php
use App\Models\Client;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::latest()->get();

        return view('clients.index', compact('clients'));
    }
}
```

---

## Step 7 — Test It End to End

Run the dev server if it is not already running:

```bash
npm run dev
php artisan serve
```

Now walk through the full flow:

**1. Visit the client list:**
Go to `http://localhost:8000/clients`. You should see the empty state with "Add your first client."

**2. Open the create form:**
Click **+ Add client** — the create form loads at `/clients/create`.

**3. Test real-time validation:**
Click into the name field, type something, then delete it all and click away. The "required" error appears immediately — no submit needed. Type an invalid email format — the error updates in real time.

**4. Test duplicate email validation:**
Enter an email that already exists in the database. The `unique:clients,email` rule catches it the moment you finish typing (because `wire:model.live`).

**5. Submit the form:**
Fill in all required fields and click **Save client**. The button briefly shows "Saving..." and then you land back on `/clients` with the green success message at the top.

**6. Verify in the list:**
Your new client appears in the list with the correct status badge.

---

## Understanding `wire:model` vs `wire:model.live`

This is a question everyone asks on Day 07.

```blade
{{-- Validates only when save() is called --}}
<flux:input wire:model="name" />

{{-- Validates after every keystroke --}}
<flux:input wire:model.live="name" />

{{-- Validates when the user leaves the field (on blur) --}}
<flux:input wire:model.blur="name" />
```

| Modifier | When validation triggers | Best used for |
|---|---|---|
| `wire:model` | On form submit | Optional fields, textarea, select |
| `wire:model.live` | Every keystroke | Critical fields — email, name |
| `wire:model.blur` | When user leaves the field | A balanced middle ground |

For FreelanceFlow we use `.live` on name and email (the most important fields) and plain `wire:model` on the rest to avoid overwhelming the user with errors on optional inputs.

---

## Understanding `#[Rule]` Validation

The `#[Rule]` attribute replaces the old `rules()` array method. Compare:

```php
// Old way — Livewire 2/3 style
protected function rules(): array
{
    return [
        'name'  => 'required|string|max:255',
        'email' => 'required|email',
    ];
}

// New way — Livewire 4 with attributes
#[Rule('required|string|max:255')]
public string $name = '';

#[Rule('required|email')]
public string $email = '';
```

The attribute approach keeps validation rules right next to the property they validate. No scanning back and forth between the rules array and the property list. Cleaner, more readable, and the approach Livewire 4 recommends.

---

## What We Learned Today

- The Livewire CRUD pattern: property → `wire:model` → action → validate → save → redirect
- `#[Rule]` attributes for declaring validation directly on Livewire properties
- `wire:model.live` for real-time validation vs `wire:model` for on-submit validation
- `wire:loading` and `wire:target` for button loading states scoped to specific actions
- `<flux:error name="field">` for automatic validation error display
- `session()->flash()` for one-time success messages that survive a redirect
- `$this->redirect(route(...), navigate: true)` for Livewire-aware redirects
- Status badge with PHP `match()` expression inside `@php` for clean conditional classes

---

## Day 08 — CRUD: Update & Delete

The create loop is done. Tomorrow we build the **edit form** and the **delete action**. We will:

- Build an `Edit` Livewire component that pre-fills the form with existing client data
- Wire up the `update()` action to save changes
- Add a delete confirmation using a Flux modal before permanently removing a client
- Handle soft deletes so nothing is permanently lost immediately

See you on Day 08.