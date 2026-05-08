# Day 14 — Flash Messages & Sessions

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 1 — Foundations**
> **Read time:** 14 min · **Level:** Beginner to Intermediate

---

> *"Every action a user takes deserves feedback. Save a client — tell them it worked. Delete a record — confirm it is gone. Hit an error — explain what happened. Today we build a complete notification system for FreelanceFlow using sessions, Livewire dispatch events, and our own flash component."*

---

## Where We Are

On Day 10 we built the `<x-flash-message />` component and dropped it into the app layout. It reads `session('success')` and `session('error')` and renders the right alert automatically.

That works — but it only covers two scenarios. FreelanceFlow needs more:

- **Success** — client saved, project created, invoice sent
- **Error** — something went wrong that is not a validation error
- **Warning** — action completed but with a caveat
- **Info** — neutral information, nothing good or bad happened

And there is another problem. When you redirect from a Livewire component, the flash message survives because it lives in the session. But what about actions that do not redirect — like inline deletes or status toggles? Livewire re-renders without a redirect and the session flash never fires.

Today we solve both problems properly.

---

## What We Are Building Today

1. A **four-type flash message component** — success, error, warning, info
2. **Multiple messages in a single request** — stack them cleanly
3. **Livewire dispatch events** for in-component notifications that do not redirect
4. A **Livewire notification component** that listens for dispatched events
5. **Session persistence** with `keep()` for multi-step flows

---

## Part 1 — Four-Type Flash Messages

Update the `<x-flash-message />` component to handle four message types, each with its own colour and icon.

Open `app/View/Components/FlashMessage.php` — no changes needed here, the class stays simple.

Open `resources/views/components/flash-message.blade.php` and replace it entirely:

```blade
@php
    $types = [
        'success' => [
            'bg'     => 'bg-green-50',
            'border' => 'border-green-200',
            'text'   => 'text-green-800',
            'icon'   => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />',
        ],
        'error' => [
            'bg'     => 'bg-red-50',
            'border' => 'border-red-200',
            'text'   => 'text-red-800',
            'icon'   => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />',
        ],
        'warning' => [
            'bg'     => 'bg-yellow-50',
            'border' => 'border-yellow-200',
            'text'   => 'text-yellow-800',
            'icon'   => '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />',
        ],
        'info' => [
            'bg'     => 'bg-blue-50',
            'border' => 'border-blue-200',
            'text'   => 'text-blue-800',
            'icon'   => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />',
        ],
    ];
@endphp

@foreach ($types as $type => $style)
    @if (session($type))
        <div class="mb-3 flex items-start gap-3 rounded-lg {{ $style['bg'] }} border {{ $style['border'] }} px-4 py-3 text-sm {{ $style['text'] }}">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                {!! $style['icon'] !!}
            </svg>
            <span>{{ session($type) }}</span>
        </div>
    @endif
@endforeach
```

Now you can flash any of the four types from a Livewire action or controller:

```php
// In a Livewire action or controller method
session()->flash('success', 'Client saved successfully.');
session()->flash('error', 'Something went wrong. Please try again.');
session()->flash('warning', 'Client saved, but the welcome email failed to send.');
session()->flash('info', 'No changes were made.');
```

The component loops over all four types and renders whichever ones have a session value — the others are silently skipped.

---

## Part 2 — Multiple Messages in One Request

Sometimes a single action needs to communicate multiple things. A batch import that partially succeeded. An update that saved but triggered a warning.

Use an array instead of a string:

```php
// Stack multiple messages of the same type
session()->flash('success', 'Client updated.');
session()->flash('warning', 'The welcome email could not be sent — check your mail config.');
```

Each type can carry one message at a time. If you need multiple messages of the same type — for example, multiple warnings — use an array:

Update the flash component to handle arrays:

```blade
@foreach ($types as $type => $style)
    @if (session($type))
        @php
            // Normalise to array so we handle both string and array values
            $messages = is_array(session($type)) ? session($type) : [session($type)];
        @endphp

        @foreach ($messages as $message)
            <div class="mb-3 flex items-start gap-3 rounded-lg {{ $style['bg'] }} border {{ $style['border'] }} px-4 py-3 text-sm {{ $style['text'] }}">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    {!! $style['icon'] !!}
                </svg>
                <span>{{ $message }}</span>
            </div>
        @endforeach
    @endif
@endforeach
```

Now flash an array of warnings:

```php
session()->flash('warning', [
    'The phone number format looks unusual.',
    'Company name contains special characters.',
]);
```

Both warnings render as separate alert blocks, each with the same yellow style.

---

## Part 3 — The Session Flash Problem with Livewire

Here is a limitation that will catch you eventually: `session()->flash()` only works when there is a redirect.

```php
// This works — there is a redirect
public function save(): void
{
    Client::create([...]);
    session()->flash('success', 'Client saved.');
    $this->redirect(route('clients.index'), navigate: true); // ← flash survives this
}

// This does NOT work — no redirect, Livewire just re-renders
public function toggleStatus(): void
{
    $this->client->update(['status' => 'inactive']);
    session()->flash('success', 'Status updated.'); // ← this disappears on re-render
}
```

When Livewire re-renders without a redirect, the session flash is written and immediately discarded on the same render cycle. The user sees nothing.

The solution is Livewire's `dispatch()` system.

---

## Part 4 — In-Component Notifications with dispatch()

Livewire components can broadcast events to other components on the same page using `dispatch()`. We use this to show notifications for actions that do not redirect.

### Step 1 — Build a Notification Livewire Component

```bash
php artisan make:livewire Notification --class
```

Open `app/Livewire/Notification.php`:

```php
<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Notification extends Component
{
    public ?string $message = null;
    public string  $type    = 'success';
    public bool    $visible = false;

    // Listen for the 'notify' event dispatched by any Livewire component
    #[On('notify')]
    public function show(string $message, string $type = 'success'): void
    {
        $this->message = $message;
        $this->type    = $type;
        $this->visible = true;
    }

    public function dismiss(): void
    {
        $this->visible  = false;
        $this->message  = null;
    }

    public function render()
    {
        return view('livewire.notification');
    }
}
```

Open `resources/views/livewire/notification.blade.php`:

```blade
@php
    $styles = [
        'success' => 'bg-green-50 border-green-200 text-green-800',
        'error'   => 'bg-red-50 border-red-200 text-red-800',
        'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
        'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
    ];
    $style = $styles[$type] ?? $styles['info'];
@endphp

@if ($visible && $message)
    <div
        class="fixed bottom-4 right-4 z-50 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-md max-w-sm {{ $style }}"
        wire:key="notification-{{ $type }}"
    >
        <span class="flex-1">{{ $message }}</span>
        <button
            wire:click="dismiss"
            class="flex-shrink-0 opacity-60 hover:opacity-100 transition-opacity"
            aria-label="Dismiss"
        >
            &times;
        </button>
    </div>
@endif
```

### Step 2 — Add it to the App Layout

Add the notification component once in `resources/views/layouts/app.blade.php` — above `@fluxScripts`:

```blade
<body class="min-h-screen bg-gray-50 font-sans antialiased">

    @include('partials.navbar')

    <div class="flex">
        @include('partials.sidebar')
        <main class="flex-1 p-6">
            <x-flash-message />
            @yield('content')
        </main>
    </div>

    {{-- Notification toast — listens for dispatch('notify') events --}}
    <livewire:notification />

    @fluxScripts
</body>
```

### Step 3 — Dispatch from Any Livewire Component

Now any Livewire component can fire a toast notification without a redirect:

```php
// In any Livewire component

// Toggle client status — no redirect needed
public function toggleStatus(): void
{
    $newStatus = $this->client->status === 'active' ? 'inactive' : 'active';

    $this->client->update(['status' => $newStatus]);

    // Dispatch a notification event — the Notification component listens for this
    $this->dispatch('notify',
        message: "Client marked as {$newStatus}.",
        type: 'success'
    );
}

// Something went wrong
public function export(): void
{
    try {
        // export logic
        $this->dispatch('notify', message: 'Export complete.', type: 'success');
    } catch (\Exception $e) {
        $this->dispatch('notify', message: 'Export failed. Try again.', type: 'error');
    }
}
```

The `#[On('notify')]` attribute in the `Notification` component listens for any `notify` event dispatched anywhere on the page and immediately renders the toast.

---

## Part 5 — session()->keep() for Multi-Step Flows

Sometimes you need a flash message to survive more than one redirect. The classic example: a multi-step form where step 1 redirects to step 2 which redirects to the success page — but you want the success message to appear on step 3.

By default, a flash message lives for exactly one redirect. Use `keep()` to extend its life:

```php
// Flash the message on step 1
session()->flash('success', 'Step 1 complete. Review your details.');

// On step 2 — keep the flash alive for one more redirect
session()->keep(['success']);

// The message finally renders on step 3 after the final redirect
```

`keep()` is also useful when validation fails on a redirect-based form — the message survives the bounce back to the form.

For FreelanceFlow, we do not have multi-step flows yet — that comes in the invoice builder. But knowing `keep()` exists means you will reach for it correctly when you need it.

---

## Part 6 — Auto-Dismiss Notifications

The notification toast we built requires the user to click × to dismiss it. For success messages, an auto-dismiss after a few seconds is better UX.

Add an auto-dismiss timer using Livewire's `js()` method:

```php
// In the Notification component
#[On('notify')]
public function show(string $message, string $type = 'success'): void
{
    $this->message = $message;
    $this->type    = $type;
    $this->visible = true;

    // Auto-dismiss success and info after 4 seconds
    if (in_array($type, ['success', 'info'])) {
        $this->js('setTimeout(() => $wire.dismiss(), 4000)');
    }
}
```

`$this->js()` executes a JavaScript snippet in the browser after the component renders. `$wire.dismiss()` calls the `dismiss()` method on the Livewire component from JavaScript.

Success and info notifications disappear after 4 seconds. Error and warning notifications stay until dismissed — they require the user's attention.

---

## When to Use What

| Scenario | Use |
|---|---|
| Action redirects (save, delete) | `session()->flash('success', '...')` |
| Action does not redirect (toggle, inline update) | `$this->dispatch('notify', ...)` |
| Multi-step flow, message survives multiple redirects | `session()->keep(['success'])` |
| Multiple messages of same type | `session()->flash('warning', ['msg1', 'msg2'])` |
| Need user to explicitly dismiss | Error and warning toasts (no auto-dismiss) |
| Can auto-dismiss | Success and info toasts (4 seconds) |

---

## Flash Helper — Centralise in a Trait

As FreelanceFlow grows, you will dispatch notifications and flash messages from many Livewire components. Avoid repeating `$this->dispatch('notify', ...)` everywhere by creating a trait:

```php
<?php

// app/Livewire/Concerns/WithNotifications.php
namespace App\Livewire\Concerns;

trait WithNotifications
{
    public function notifySuccess(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function notifyError(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'error');
    }

    public function notifyWarning(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'warning');
    }

    public function notifyInfo(string $message): void
    {
        $this->dispatch('notify', message: $message, type: 'info');
    }
}
```

Use it in any Livewire component:

```php
class Create extends Component
{
    use WithNotifications;

    public function save(): void
    {
        $this->validate();
        Client::create([...]);

        // Instead of $this->dispatch('notify', message: '...', type: 'success')
        $this->notifySuccess('Client added successfully.');

        $this->redirect(route('clients.index'), navigate: true);
    }
}
```

Clean, consistent, one place to change the implementation.

---

## What We Learned Today

- **Four flash types** — success, error, warning, info — each with its own colour and icon in the component
- **Array flash messages** — stack multiple messages of the same type using `session()->flash('warning', [...])`
- **The redirect-only limitation** of `session()->flash()` — it disappears on Livewire re-renders without a redirect
- **`dispatch()` and `#[On()]`** — Livewire's event system for in-component notifications that do not redirect
- **Auto-dismiss with `$this->js()`** — success and info toasts dismiss after 4 seconds, error and warning stay
- **`session()->keep()`** — extend flash message life across multiple redirects for multi-step flows
- **`WithNotifications` trait** — centralise dispatch logic for consistent notifications across the app

FreelanceFlow now has a complete feedback system. Every action tells the user what happened, how severe it is, and clears itself appropriately.

---

## Day 15 — Phase 1 Review & GitHub Push

Tomorrow is the Phase 1 milestone. We review and refactor everything built in Days 01–14, clean up any inconsistencies, write a proper README, and push FreelanceFlow v0.1 to a public GitHub repository. From Day 16 we move into Phase 2 — Eloquent relationships, projects, invoices, and the features that make FreelanceFlow a real product.

See you on Day 15.