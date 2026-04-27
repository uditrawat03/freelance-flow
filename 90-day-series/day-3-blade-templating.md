# Day 3 — Blade Templating — Building the App Shell

> **Series:** Laravel Zero to Hero · **Phase 1 — Foundations** · April 29, 2026

---

> *"Every page in FreelanceFlow needs a navbar, a sidebar, a footer. Copy-pasting that HTML into every file is how projects become impossible to maintain. Blade layouts solve this completely — change the navbar in one place and every page in your app updates instantly."*

---

## Recap & Context

On Day 2 we built the client list page — but it's raw, unstyled HTML with no shared layout. Today we fix that by building FreelanceFlow's **app shell**: the master layout every page in the app will inherit from. This is one of the most satisfying days in the whole series — by the end you'll have a real, professional-looking app structure.

---

## What is Blade?

Blade is Laravel's templating engine. It lets you write clean, readable HTML with special directives — lines that start with `@` — that add logic, inheritance, and reusability without mixing raw PHP into your markup.

Blade files have the extension `.blade.php` and live in `resources/views/`. When Laravel renders them, it compiles them into plain PHP and caches the result — so you get developer-friendly syntax with zero performance cost.

Here are the core directives we'll use today:

| Directive | What it does |
|---|---|
| `@extends` | Inherit a master layout |
| `@yield` | Define a named slot in the layout |
| `@section` / `@endsection` | Fill a slot from the child view |
| `@include` | Pull in a reusable partial |
| `{{ }}` | Print a variable safely (XSS-escaped) |
| `@forelse` / `@empty` | Loop with a built-in empty state |

---

## What We're Building Today

We'll create a master layout, two partials (navbar and sidebar), and update the client list view to use the new shell:

```
resources/views/
├── layouts/
│   └── app.blade.php        ← master layout (new)
├── partials/
│   ├── navbar.blade.php      ← top navigation (new)
│   └── sidebar.blade.php     ← side menu (new)
└── clients/
    └── index.blade.php       ← updated to extend layout
```

---

## Step 1 — The Master Layout

Create `resources/views/layouts/app.blade.php`. This is the single file that defines the HTML skeleton every page shares — the `<head>`, the navbar, the sidebar, the footer. Child views slot their unique content into the `@yield('content')` placeholder.

```blade
<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'FreelanceFlow')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    @include('partials.navbar')

    <div class="flex items-center mt-16 z-0">
        @include('partials.sidebar')

        <main class="px-4 py-8 h-screen">
            @yield('content')
        </main>
    </div>

</body>

</html>
```

> **Notice `@yield('title', 'FreelanceFlow')`** — the second argument is a default. If a child view doesn't define a title section, it falls back to 'FreelanceFlow'. A small but professional touch.

---

## Step 2 — The Navbar Partial

Create `resources/views/partials/navbar.blade.php`. This is pulled into the master layout with `@include` — change it once, it updates everywhere.

```blade
<!-- resources/views/partials/navbar.blade.php -->
<nav class="flex items-center justify-between bg-white w-full h-16 py-2 px-4 shadow-2xl fixed top-0 left-0 right-0 z-9999">
    <a href="/" class="text-2xl font-bold">FreelanceFlow</a>

    <div class="flex items-center gap-4">
        <a href="{{ route('clients.index') }}" class="hover:text-blue-600">Clients</a>
        <a href="#">Projects</a>
        <a href="#">Invoices</a>
    </div>
</nav>
```

> **Always use `route()`** to generate URLs in Blade — never hardcode them. If you ever change a route path, every `route()` call updates automatically.

---

## Step 3 — The Sidebar Partial

Create `resources/views/partials/sidebar.blade.php`. We'll use `request()->routeIs()` to highlight the active nav item — a small detail that makes the app feel polished.

```blade
<!-- resources/views/partials/sidebar.blade.php -->
<aside class="w-72 py-8 shadow h-screen">
    <ul class="flex flex-col px-4 gap-4">
        <li class="{{ request()->routeIs('clients.*') ? 'active' : '' }} hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white">
            <a href="{{ route('clients.index') }}">Clients</a>
        </li>
        <li class="{{ request()->routeIs('projects.*') ? 'active' : '' }} hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white">
            <a href="#">Projects</a>
        </li>
        <li class="hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white"><a href="#">Invoices</a></li>
        <li class="hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white"><a href="#">Dashboard</a></li>
    </ul>
</aside>
```

`routeIs('clients.*')` matches any route whose name starts with `clients.` — so `clients.index`, `clients.show`, `clients.edit` all highlight the same sidebar link automatically.

---

## Step 4 — Updating the Client List View

Now update `resources/views/clients/index.blade.php` to extend the master layout. Delete all the old HTML boilerplate and replace it with two clean `@section` blocks:

```blade
<!-- resources/views/clients/index.blade.php -->
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
        <div class="client-card">
            <strong>{{ $client['name'] }}</strong>
            <span>{{ $client['email'] }}</span>
        </div>
    @empty
        <p>No clients yet.
            <a href="{{ route('clients.create') }}">Add your first one.</a>
        </p>
    @endforelse
@endsection
```

> **`@forelse` is `@foreach` with a built-in empty state.** When `$clients` is empty, the `@empty` block renders automatically. No more `@if(count($clients) > 0)` boilerplate — this is the Blade way.

Visit `http://localhost:8000/clients` — the full app shell is now wrapping the client list. Navbar, sidebar, layout inherited. One `@extends` line did all of it.

---

## How the Inheritance Chain Works

When a user visits `/clients`, here's what happens:

1. Laravel renders `clients/index.blade.php`
2. It sees `@extends('layouts.app')` → pulls in the master layout
3. The master layout's `@yield('content')` is replaced with the `@section('content')` from the child view
4. `@include` pulls navbar and sidebar partials into the layout
5. One complete, fully assembled HTML page is sent to the browser

Every future page in FreelanceFlow — projects, invoices, dashboard — follows this same pattern. One line of `@extends` and they all get the full shell for free.

---

## Blade Quick Reference

```blade
{{-- Print & escape a variable --}}
{{ $variable }}

{{-- Print raw HTML (unescaped — use carefully) --}}
{!! $html !!}

{{-- Blade comment — invisible in page source --}}
{{-- This will not appear in the browser --}}

{{-- Conditionals --}}
@if ($user->isAdmin())
    <span>Admin</span>
@elseif ($user->isManager())
    <span>Manager</span>
@else
    <span>User</span>
@endif

{{-- Loop with empty fallback --}}
@forelse ($items as $item)
    <p>{{ $item->name }}</p>
@empty
    <p>Nothing here yet.</p>
@endforelse

{{-- Include a partial and pass data --}}
@include('partials.alert', ['message' => 'Saved!'])

{{-- Check the active route --}}
request()->routeIs('clients.*')
```

---

## What's Next

The app shell is built. Tomorrow on **Day 4** we design the real database schema for FreelanceFlow — the `clients` table, what columns it needs, and how Laravel's migration system gives you version control for your database. No more hardcoded arrays.