# Day 10 — Blade Components & Slots — Building FreelanceFlow's UI System

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 15 min · **Level:** Beginner to Intermediate

---

> *"Look at the code we have written so far. The page header pattern — a title, subtitle, and action button — appears on every page. The flash message appears in two places. The status badge logic is duplicated in the list. Any time you copy and paste HTML, you are creating a maintenance problem. Today we fix that with Blade components."*

---

## Where We Are

Nine days in. FreelanceFlow has working CRUD, real-time validation, soft deletes, and custom error messages. The functionality is solid. The code structure, however, has a problem — repetition.

Look at these patterns that appear across multiple files right now:

- **Page header** — title, subtitle, action button — copied into every page view
- **Flash success message** — the same green alert HTML in `index.blade.php`
- **Status badge** — the `match()` expression and the span markup duplicated
- **Form card wrapper** — `<flux:card class="max-w-2xl p-6 space-y-5">` repeated in create and edit

Every one of these is a copy-paste waiting to break. Change the flash message style in one place and it stays wrong everywhere else.

Blade components solve this. Write once, use everywhere, maintain in one file.

---

## What We Are Building Today

1. `<x-page-header>` — reusable page header with title, subtitle, and optional action slot
2. `<x-flash-message>` — success and error flash message component
3. `<x-client-status>` — status badge component for the client list
4. `<x-form-card>` — wrapper card for all FreelanceFlow forms
5. `<x-empty-state>` — reusable empty list state with message and CTA

---

## How Blade Components Work

A Blade component is a PHP class paired with a Blade view. You create it with artisan, define public properties as its API, and use it in any Blade file with the `<x-component-name>` tag.

```bash
php artisan make:component PageHeader
```

This creates two files:
- `app/View/Components/PageHeader.php` — the component class
- `resources/views/components/page-header.blade.php` — the view

Use it anywhere with:

```blade
<x-page-header title="Clients" subtitle="Manage all your clients." />
```

That is the entire pattern. Now let us build the components FreelanceFlow actually needs.

---

## Component 1 — Page Header

Every page in FreelanceFlow has a header: a title on the left, an optional subtitle below it, and an optional action button on the right. Right now this HTML is repeated in every view file.

Create the component:

```bash
php artisan make:component PageHeader
```

Open `app/View/Components/PageHeader.php`:

```php
<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PageHeader extends Component
{
    public function __construct(
        public string  $title,
        public string  $subtitle = '',
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.page-header');
    }
}
```

Open `resources/views/components/page-header.blade.php`:

```blade
<div class="flex items-start justify-between mb-6">

    {{-- Left: title and subtitle --}}
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    {{-- Right: optional action button slot --}}
    @if ($slot->isNotEmpty())
        <div class="shrink-0">
            {{ $slot }}
        </div>
    @endif

</div>
```

Now use it in `resources/views/clients/index.blade.php`:

```blade
{{-- Before: 9 lines of repeated HTML --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Clients</h1>
        <p class="mt-1 text-sm text-gray-500">Manage all your clients.</p>
    </div>
    <a href="{{ route('clients.create') }}" class="...">+ Add client</a>
</div>

{{-- After: clean, readable, maintainable --}}
<x-page-header title="Clients" subtitle="Manage all your clients.">
    <a href="{{ route('clients.create') }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors">
        + Add client
    </a>
</x-page-header>
```

The content between `<x-page-header>` and `</x-page-header>` is the `$slot` — it renders in the right side of the header. If you use the component without any content inside, `$slot->isNotEmpty()` returns false and the right side is hidden entirely.

Use the same component on the edit page:

```blade
<x-page-header
    title="Edit Client"
    subtitle="Update {{ $client->name }}'s details."
>
    <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
        ← Back to clients
    </a>
</x-page-header>
```

And on the create page:

```blade
<x-page-header title="Add Client" subtitle="Fill in the details to add a new client.">
    <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
        ← Back to clients
    </a>
</x-page-header>
```

---

## Component 2 — Flash Message

The success message appears after every save and delete. Right now it is 8 lines of HTML copied in every view that uses it. Extract it:

```bash
php artisan make:component FlashMessage
```

Open `app/View/Components/FlashMessage.php`:

```php
<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FlashMessage extends Component
{
    public function render(): View|Closure|string
    {
        return view('components.flash-message');
    }
}
```

Open `resources/views/components/flash-message.blade.php`:

```blade
{{-- Success message --}}
@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
        </svg>
        {{ session('success') }}
    </div>
@endif

{{-- Error message --}}
@if (session('error'))
    <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5a1 1 0 112 0v-4a1 1 0 10-2 0v4zm1-8a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
        </svg>
        {{ session('error') }}
    </div>
@endif
```

This handles both `success` and `error` flash messages in a single component. Now replace the flash HTML in every view with one tag:

```blade
{{-- Before --}}
@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 ...">
        ...
    </div>
@endif

{{-- After --}}
<x-flash-message />
```

Even better — move it into the main app layout so it appears automatically on every page without needing to be added to each view individually:

```blade
{{-- resources/views/layouts/app.blade.php --}}
<main class="flex-1 p-6">
    <x-flash-message />   {{-- appears on every page automatically --}}
    @yield('content')
</main>
```

Now remove `<x-flash-message />` from individual view files — the layout handles it.

---

## Component 3 — Client Status Badge

The status badge uses a `match()` expression to map status strings to CSS classes. This logic is duplicated in the client list and will appear again in the client detail view. Extract it:

```bash
php artisan make:component ClientStatus
```

Open `app/View/Components/ClientStatus.php`:

```php
<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ClientStatus extends Component
{
    public string $badgeClass;

    public function __construct(public string $status)
    {
        $this->badgeClass = match($status) {
            'active'   => 'bg-green-100 text-green-700',
            'inactive' => 'bg-gray-100 text-gray-600',
            'lead'     => 'bg-yellow-100 text-yellow-700',
            default    => 'bg-gray-100 text-gray-600',
        };
    }

    public function render(): View|Closure|string
    {
        return view('components.client-status');
    }
}
```

Open `resources/views/components/client-status.blade.php`:

```blade
<span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $badgeClass }}">
    {{ ucfirst($status) }}
</span>
```

Use it in the client list:

```blade
{{-- Before --}}
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

{{-- After --}}
<x-client-status :status="$client->status" />
```

The `:status` syntax (with the colon prefix) passes a PHP variable or expression as the prop value. Without the colon, it passes a plain string. This is one of the most common beginner confusions with Blade components — remember:

```blade
{{-- Passes the string "active" --}}
<x-client-status status="active" />

{{-- Passes the value of the $client->status variable --}}
<x-client-status :status="$client->status" />
```

---

## Component 4 — Form Card

Every form in FreelanceFlow is wrapped in a Flux card with the same max-width and padding. When we start building the project and invoice forms later, this wrapper will appear again. Extract it now:

```bash
php artisan make:component FormCard
```

`app/View/Components/FormCard.php`:

```php
<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FormCard extends Component
{
    public function __construct(public string $maxWidth = 'max-w-2xl') {}

    public function render(): View|Closure|string
    {
        return view('components.form-card');
    }
}
```

`resources/views/components/form-card.blade.php`:

```blade
<flux:card class="{{ $maxWidth }} p-6 space-y-5">
    {{ $slot }}
</flux:card>
```

Use it in the create and edit Livewire views:

```blade
{{-- Before --}}
<flux:card class="max-w-2xl p-6 space-y-5">
    ... form fields ...
</flux:card>

{{-- After --}}
<x-form-card>
    ... form fields ...
</x-form-card>

{{-- Wider card for a future invoice form --}}
<x-form-card max-width="max-w-4xl">
    ... invoice fields ...
</x-form-card>
```

---

## Component 5 — Empty State

The empty state on the client list — "No clients yet, add your first" — will appear on every list page in the app. Projects, invoices, all of them will have this same pattern.

```bash
php artisan make:component EmptyState
```

`app/View/Components/EmptyState.php`:

```php
<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EmptyState extends Component
{
    public function __construct(
        public string $message = 'Nothing here yet.',
        public string $ctaText = '',
        public string $ctaHref = '',
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.empty-state');
    }
}
```

`resources/views/components/empty-state.blade.php`:

```blade
<div class="text-center py-16">
    <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-4">
        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4" />
        </svg>
    </div>
    <p class="text-sm text-gray-500">{{ $message }}</p>
    @if ($ctaText && $ctaHref)
        <a href="{{ $ctaHref }}" class="mt-3 inline-block text-sm text-indigo-600 hover:underline font-medium">
            {{ $ctaText }}
        </a>
    @endif
</div>
```

Use it in the client list:

```blade
{{-- Before --}}
<div class="text-center py-16">
    <p class="text-gray-400 text-sm">No clients yet.</p>
    <a href="{{ route('clients.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">
        Add your first client
    </a>
</div>

{{-- After --}}
<x-empty-state
    message="No clients yet."
    cta-text="Add your first client"
    :cta-href="route('clients.create')"
/>
```

> Note: multi-word prop names use kebab-case in the HTML (`cta-text`) but camelCase in the PHP constructor (`$ctaText`). Laravel handles the conversion automatically.

---

## The Updated Client List — Clean and Maintainable

After applying all five components, `clients/index.blade.php` looks like this:

```blade
@extends('layouts.app')

@section('title', 'Clients — FreelanceFlow')

@section('content')

    <x-page-header
        title="Clients"
        subtitle="{{ $clients->count() }} {{ Str::plural('client', $clients->count()) }}"
    >
        <a
            href="{{ route('clients.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors"
        >
            + Add client
        </a>
    </x-page-header>

    @forelse ($clients as $client)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-3 flex items-center justify-between">
            <div>
                <p class="font-medium text-gray-900">{{ $client->name }}</p>
                <p class="text-sm text-gray-500">{{ $client->email }}</p>
                @if ($client->company)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $client->company }}</p>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <x-client-status :status="$client->status" />
                <a href="{{ route('clients.edit', $client) }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
            </div>
        </div>
    @empty
        <x-empty-state
            message="No clients yet."
            cta-text="Add your first client"
            :cta-href="route('clients.create')"
        />
    @endforelse

@endsection
```

Compare this to what the file looked like at the start of today. Same functionality. A third of the lines. Every part has one job.

---

## Anonymous Components vs Class Components

You may notice we used `php artisan make:component ComponentName` which creates both a PHP class and a Blade view. Laravel also supports **anonymous components** — just a Blade file with no PHP class.

```bash
# Class component — PHP class + Blade view
php artisan make:component PageHeader

# Anonymous component — Blade view only, placed in resources/views/components/
# No artisan command needed, just create the file
touch resources/views/components/divider.blade.php
```

Use anonymous components for purely presentational elements with no logic:

```blade
{{-- resources/views/components/divider.blade.php --}}
<hr class="my-6 border-gray-200">

{{-- Used anywhere as: --}}
<x-divider />
```

Use class components when the component needs logic — property validation, computed values, database queries, or a `match()` expression like our status badge.

---

## Component Reference — What We Built

| Component | Tag | Props | Slot |
|---|---|---|---|
| Page Header | `<x-page-header>` | `title`, `subtitle` | Action button |
| Flash Message | `<x-flash-message />` | none | none |
| Client Status | `<x-client-status />` | `:status` | none |
| Form Card | `<x-form-card>` | `max-width` | Form fields |
| Empty State | `<x-empty-state />` | `message`, `cta-text`, `:cta-href` | none |

---

## What We Learned Today

- **Blade components** — `php artisan make:component Name` creates a PHP class + Blade view pair
- **`$slot`** — the default slot renders content placed between the component tags
- **`$slot->isNotEmpty()`** — conditionally render the slot wrapper only when content exists
- **Prop syntax** — `:prop="$variable"` passes PHP values, `prop="string"` passes plain strings
- **Kebab-to-camelCase conversion** — `cta-text` in HTML becomes `$ctaText` in PHP automatically
- **Anonymous components** — Blade-only components in `resources/views/components/` for simple presentational elements
- **Moving flash messages to the layout** — one tag in the layout renders flash messages on every page

FreelanceFlow now has a small but solid UI component system. Every page uses the same building blocks. Change one component file — every page using it updates instantly.

---

## Day 11 — Flash Messages & Sessions

Tomorrow we stop filling FreelanceFlow with data by hand. We will write a `ClientFactory` using Faker to generate realistic client records, build a `DatabaseSeeder` that seeds 50 clients in one command, and learn how factories and seeders fit into your testing workflow from Day 45 onwards.

See you on Day 11.