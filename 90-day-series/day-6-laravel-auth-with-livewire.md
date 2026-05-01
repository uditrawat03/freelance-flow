# Day 06 — Manual Auth with Livewire + Flux UI + Tailwind 4

> **Series:** FreelanceFlow — Laravel zero to hero · **Phase 1 — Foundations**
> **Read time:** 16 min · **Level:** Beginner to Intermediate

---

> *"By Day 05 FreelanceFlow could read real clients from a real database. Today we lock the door. No scaffolding, no generators — we build login and registration ourselves using Livewire components and Flux UI. You will understand every single line because you wrote every single line."*

---

## Where We Are

Five days in. FreelanceFlow can:

- Serve pages through routes and controllers
- Render clean layouts using Blade
- Read real clients from MySQL via Eloquent
- Display a client list at `/clients`

What it cannot do yet: know who you are. Today we fix that. We are building login and register from scratch — manually — using the modern Laravel stack: **Livewire 4 + Flux UI + Tailwind 4**.

No scaffolding. No `breeze:install`. No generated files you do not understand.

---

## The Stack We Are Using

| Tool | Role | Why |
|---|---|---|
| **Livewire 4** | PHP-first reactive components | Handle form state, validation, and submission without writing JavaScript |
| **Flux UI** | Component library | Pre-built, accessible inputs, buttons, and cards that look great out of the box |
| **Tailwind 4** | Utility CSS | Layout and spacing — Flux handles the heavy design work |

---

## Also — The Middleware Fix

Before we build anything, let us address the error many of you saw if you tried the old approach:

```
Call to undefined method App\Http\Controllers\ClientController::middleware()
```

That method was removed in Laravel 11. The correct pattern now is middleware on the route:

```php
// ✓ routes/web.php — correct for current Laravel
Route::resource('clients', ClientController::class)
    ->middleware('auth');
```

Or using the `HasMiddleware` interface inside the controller:

```php
// ✓ controller-based — also correct
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ClientController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }
}
```

We use the route approach for FreelanceFlow. Now let us build the auth system.

---

## Step 1 — Install Livewire 4

Livewire uses Laravel's package auto-discovery. Installation is one command:

```bash
composer require livewire/livewire
```

That is it. No artisan commands. No config setup needed.

---

## Step 2 — Install Flux UI (Free Tier)

Flux has a generous free tier that includes all the components we need — inputs, buttons, cards, form labels, error messages. Install it:

```bash
composer require livewire/flux
```

> **Flux free vs Flux Pro:** The free tier includes all essential components — inputs, buttons, dropdowns, modals, and form controls. Flux Pro adds date pickers, charts, and advanced data components. The free tier is everything we need for this series.

---

## Step 3 — Install and Configure Tailwind 4

Install Tailwind and Vite:

```bash
npm install tailwindcss @tailwindcss/vite
```

Update `vite.config.js` to include the Tailwind plugin:

```js
// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
})
```

Update `resources/css/app.css` to import Tailwind and Flux styles:

```css
/* resources/css/app.css */
@import 'tailwindcss';

@import '../../vendor/livewire/flux/dist/flux.css';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: Inter, sans-serif;
}
```

---

## Step 4 — Update the Layout File

Open `resources/views/layouts/app.blade.php` and add the required Flux directives. Flux needs `@fluxAppearance` in the `<head>` and `@fluxScripts` before `</body>`. Also add the Inter font:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'FreelanceFlow')</title>

    {{-- Inter font --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Required for Flux dark mode support --}}
    @fluxAppearance
</head>
<body class="min-h-screen font-sans antialiased">

    @include('partials.navbar')

    <div class="flex">
        @auth
            @include('partials.sidebar')
        @endauth


        <main class="flex-1 p-6">
            @yield('content')
        </main>
    </div>

    {{-- Required: Flux JS + Livewire assets --}}
    @fluxScripts
</body>
</html>
```

Create a separate auth layout for the login and register pages — cleaner than reusing the app shell:

```bash
mkdir -p resources/views/layouts
```

Create `resources/views/layouts/auth.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'FreelanceFlow')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen font-sans antialiased flex items-center justify-center">

    {{ $slot }}

    @fluxScripts
</body>
</html>
```

---

## Step 5 — Add Auth Routes

Open `routes/web.php` and add the auth routes:

```php
// routes/web.php
use App\Http\Controllers\ClientController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->middleware('auth')->name('logout');

// Protected routes
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::resource('clients', ClientController::class)
    ->middleware('auth');
```

Create the dashboard view `resources/views/dashboard.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Dashboard — FreelanceFlow')

@section('content')
    <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
    <p class="mt-2 text-gray-500">Welcome back, {{ auth()->user()->name }}.</p>
@endsection
```

---

## Step 6 — Build the Login Livewire Component

Generate the Livewire component:

```bash
php artisan make:livewire Auth/Login --class
```

This creates two files:
- `app/Livewire/Auth/Login.php`
- `resources/views/livewire/auth/login.blade.php`

Open `app/Livewire/Auth/Login.php` and write the logic:

```php
<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('layouts.auth')]
class Login extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|min:8')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
```

Now open `resources/views/livewire/auth/login.blade.php` and build the UI with Flux components:

```blade
<div class="w-full max-w-sm mx-auto">

    {{-- Logo / Brand --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">FreelanceFlow</h1>
        <p class="mt-1 text-sm text-gray-500">Sign in to your account</p>
    </div>

    {{-- Card --}}
    <flux:card class="p-6 space-y-5">

        {{-- Email --}}
        <flux:field>
            <flux:label>Email address</flux:label>
            <flux:input
                wire:model="email"
                type="email"
                placeholder="you@example.com"
                autofocus
                autocomplete="email"
            />
            <flux:error name="email" />
        </flux:field>

        {{-- Password --}}
        <flux:field>
            <div class="flex items-center justify-between">
                <flux:label>Password</flux:label>
                <a href="#" class="text-xs text-indigo-600 hover:underline">Forgot password?</a>
            </div>
            <flux:input
                wire:model="password"
                type="password"
                placeholder="••••••••"
                autocomplete="current-password"
            />
            <flux:error name="password" />
        </flux:field>

        {{-- Remember me --}}
        <div class="flex items-center gap-2">
            <flux:checkbox wire:model="remember" id="remember" />
            <flux:label for="remember" class="text-sm font-normal">Remember me</flux:label>
        </div>

        {{-- Submit --}}
        <flux:button wire:click="login" wire:loading.attr="disabled" variant="primary" class="w-full">
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in...</span>
        </flux:button>

    </flux:card>

    {{-- Register link --}}
    <p class="mt-4 text-center text-sm text-gray-500">
        Don't have an account?
        <a href="{{ route('register') }}" class="text-indigo-600 hover:underline font-medium">Create one</a>
    </p>

</div>
```

Visit `http://localhost:8000/login` — the login form is live, built entirely by you.

---

## Step 7 — Build the Register Livewire Component

Generate the component:

```bash
php artisan make:livewire Auth/Register --class
```

Open `app/Livewire/Auth/Register.php`:

```php
<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('layouts.auth')]
class Register extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Rule('required|min:8|confirmed')]
    public string $password = '';

    #[Rule('required')]
    public string $password_confirmation = '';

    public function register(): void
    {
        $this->validate();

        $user = User::create([
            'name'     => $this->name,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
```

Open `resources/views/livewire/auth/register.blade.php`:

```blade
<div class="w-full max-w-sm mx-auto">

    {{-- Brand --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">FreelanceFlow</h1>
        <p class="mt-1 text-sm text-gray-500">Create your account</p>
    </div>

    {{-- Card --}}
    <flux:card class="p-6 space-y-5">

        {{-- Name --}}
        <flux:field>
            <flux:label>Full name</flux:label>
            <flux:input
                wire:model="name"
                type="text"
                placeholder="John Doe"
                autofocus
                autocomplete="name"
            />
            <flux:error name="name" />
        </flux:field>

        {{-- Email --}}
        <flux:field>
            <flux:label>Email address</flux:label>
            <flux:input
                wire:model="email"
                type="email"
                placeholder="you@example.com"
                autocomplete="email"
            />
            <flux:error name="email" />
        </flux:field>

        {{-- Password --}}
        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input
                wire:model="password"
                type="password"
                placeholder="Min. 8 characters"
                autocomplete="new-password"
            />
            <flux:error name="password" />
        </flux:field>

        {{-- Confirm Password --}}
        <flux:field>
            <flux:label>Confirm password</flux:label>
            <flux:input
                wire:model="password_confirmation"
                type="password"
                placeholder="Repeat password"
                autocomplete="new-password"
            />
            <flux:error name="password_confirmation" />
        </flux:field>

        {{-- Submit --}}
        <flux:button wire:click="register" wire:loading.attr="disabled" variant="primary" class="w-full">
            <span wire:loading.remove>Create account</span>
            <span wire:loading>Creating account...</span>
        </flux:button>

    </flux:card>

    {{-- Login link --}}
    <p class="mt-4 text-center text-sm text-gray-500">
        Already have an account?
        <a href="{{ route('login') }}" class="text-indigo-600 hover:underline font-medium">Sign in</a>
    </p>

</div>
```

Visit `http://localhost:8000/register` — fill in the form, submit, and you are logged in and redirected to the dashboard.

---

## Step 8 — Update the Navbar

Update `resources/views/partials/navbar.blade.php` with auth-aware links and a proper logout form:

```blade
<nav class="bg-black  px-6 py-3 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-lg font-bold">
        FreelanceFlow
    </a>

    <div class="flex items-center gap-4">
        @auth
            <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-red-500 transition">
                    Log out
                </button>
            </form>
        @endauth

        @guest
            <a href="{{ route('login') }}" class="text-sm text-white hover:text-blue-600">Log in</a>
            <a href="{{ route('register') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Register</a>
        @endguest
    </div>
</nav>
```

---

## Step 9 — Compile Assets and Test

```bash
npm run dev
```

Now test the full flow:

1. Visit `/clients` while logged out → redirected to `/login`
2. Go to `/register` → fill the form → automatically logged in → redirected to `/dashboard`
3. Navigate to `/clients` → client list loads
4. Click Log out → session destroyed → redirected to `/login`
5. Try `/clients` again → back to `/login`

---

## How Flux Components Work — What You Just Used

You built real auth pages today. Here is a quick reference for the Flux components you used:

| Component | What it does |
|---|---|
| `<flux:card>` | A white bordered container with shadow |
| `<flux:field>` | Groups a label, input, and error message together |
| `<flux:label>` | Styled label, links to its input via `for` |
| `<flux:input>` | Styled text input — supports all HTML input types |
| `<flux:error name="field">` | Shows the validation error for a field automatically |
| `<flux:checkbox>` | Styled checkbox |
| `<flux:button>` | Styled button — `variant="primary"` for filled style |

The `wire:model` attribute on each input binds it to the Livewire property in real time. `wire:loading` adds a loading state while the action is running. `flux:error` automatically reads from Livewire's validation errors — no extra wiring needed.

---

## Auth Helpers You Will Use Every Day

```php
// In controllers and PHP
auth()->user();     // the logged-in User model
auth()->id();       // the user's ID
auth()->check();    // true if authenticated
auth()->guest();    // true if not authenticated

// In Blade templates
@auth               // shown only to authenticated users
@guest              // shown only to guests

// Scope data to the current user — used from Day 07 onwards
$clients = Client::where('user_id', auth()->id())->get();
```

---

## What We Built Today

- **Livewire 4** installed into the existing project — one composer command
- **Flux UI free tier** installed — pre-built accessible components
- **Tailwind 4** configured with Vite and the `@tailwindcss/vite` plugin
- **Login page** — Livewire component with email, password, remember me, validation
- **Register page** — Livewire component with name, email, password confirmation, unique email validation
- **Auth layout** — separate clean layout for auth pages
- **Route protection** — `auth` middleware on the route, not the controller constructor
- **Auth-aware navbar** — `@auth` and `@guest` toggling UI

No scaffolding. No black boxes. Every file is yours.

---

## Day 07 Teaser — CRUD: Create & Read

Auth is locked in. Tomorrow we build the first real form in FreelanceFlow — the **create client page**. We will:

- Build a Blade form with Flux input components
- Write the `store()` method in ClientController
- Add validation with a Form Request class
- Save a new client to the database
- Redirect back with a flash success message

The full CRUD create loop begins. See you on Day 07.