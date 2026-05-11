# Day 23 — Laravel Notifications — One Message, Many Channels

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"On Day 20 we built a Mailable for email. On Day 22 we fired events to trigger side effects. Today we go one level higher — Laravel Notifications. One Notification class delivers the same message through multiple channels simultaneously: a database record for the bell icon in the UI, an email to the client, and optionally a Slack message to your team. Write once, deliver everywhere."*

---

## Notifications vs Mailables — What Is the Difference?

Both send email. But they are designed for different things.

| | Mailable | Notification |
|---|---|---|
| **Purpose** | Rich, standalone transactional emails | Alerts and updates through multiple channels |
| **Channels** | Email only | Database, email, Slack, SMS, broadcast |
| **Template** | Full custom Blade view | Structured message with line components |
| **Best for** | Invoices, welcome emails, receipts | Status changes, alerts, activity feeds |

For FreelanceFlow: the invoice email is a Mailable — it needs a custom PDF-style layout. A "project status changed" alert is a Notification — it should appear in the app's notification bell AND send a brief email.

---

## What We Are Building Today

1. The **notifications database table** — stores in-app notifications
2. A **`ProjectStatusChanged` notification class** — delivered via database and email
3. A **notification bell** in the FreelanceFlow navbar — shows unread count
4. A **notifications Livewire component** — lists and marks notifications as read
5. **Trigger the notification** when a project status is updated
6. The **database channel** — reading notifications in the UI

---

## Step 1 — Create the Notifications Table

```bash
php artisan notifications:table
php artisan migrate
```

This creates a `notifications` table with columns: `id` (UUID), `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), `read_at`, `created_at`, `updated_at`.

The `notifiable_id` and `notifiable_type` use Laravel's polymorphic pattern — notifications can belong to any model (User, Client, Team). For FreelanceFlow, notifications belong to the logged-in User.

---

## Step 2 — Add HasNotifications to the User Model

Open `app/Models/User.php` and confirm it uses the `Notifiable` trait:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // ... existing code
}
```

`Notifiable` adds the `notify()` method and the `notifications()` relationship to the User model. It is included by default in every fresh Laravel project.

---

## Step 3 — Create the Notification Class

```bash
php artisan make:notification ProjectStatusChanged
```

Open `app/Notifications/ProjectStatusChanged.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Project $project,
        public readonly string  $previousStatus,
    ) {}

    // Which channels to deliver through
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    // --- Database channel ---
    // Stored as JSON in the notifications table
    public function toDatabase(object $notifiable): array
    {
        return [
            'project_id'      => $this->project->id,
            'project_name'    => $this->project->name,
            'client_id'       => $this->project->client_id,
            'client_name'     => $this->project->client?->name,
            'previous_status' => $this->previousStatus,
            'new_status'      => $this->project->status,
            'status_label'    => $this->project->status_label,
            'url'             => route('clients.show', $this->project->client_id),
        ];
    }

    // --- Mail channel ---
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Project update: {$this->project->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("The status of **{$this->project->name}** has been updated.")
            ->line("**Previous status:** " . ucfirst(str_replace('_', ' ', $this->previousStatus)))
            ->line("**New status:** {$this->project->status_label}")
            ->action('View Project', route('clients.show', $this->project->client_id))
            ->line('You are receiving this because you manage this project on FreelanceFlow.');
    }

    // Called if all retries fail
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('ProjectStatusChanged notification failed', [
            'project_id' => $this->project->id,
            'error'      => $exception->getMessage(),
        ]);
    }
}
```

**Key methods:**

- `via()` — returns an array of channel names. `'database'` stores in MySQL, `'mail'` sends an email. Add `'slack'` or `'broadcast'` for additional channels
- `toDatabase()` — returns a PHP array that is JSON-encoded and stored in `notifications.data`. Put everything you need to render the notification in the UI
- `toMail()` — returns a `MailMessage` using Laravel's fluent notification mail builder. Simpler than a full Mailable — no separate view file needed for simple alerts
- `ShouldQueue` on the Notification — the entire notification (all channels) is queued as a background job

---

## Step 4 — Trigger the Notification on Status Change

Update the `Edit` Livewire component to fire the notification when status changes:

```php
// app/Livewire/Projects/Edit.php
use App\Notifications\ProjectStatusChanged;

public function update(): void
{
    $this->validate();

    // Capture the status before saving
    $previousStatus = $this->project->status;

    $this->project->update([
        'client_id'   => $this->selectedClientId,
        'name'        => $this->name,
        'description' => $this->description,
        'status'      => $this->status,
        'budget'      => $this->budget ?: null,
        'deadline'    => $this->deadline ?: null,
    ]);

    $this->project->tags()->sync($this->selectedTags);

    // Only notify if the status actually changed
    if ($previousStatus !== $this->status) {
        $this->project->loadMissing('client');

        // Notify the currently logged-in user
        // In Phase 4 we extend this to notify the client too
        auth()->user()->notify(
            new ProjectStatusChanged($this->project, $previousStatus)
        );
    }

    session()->flash('success', 'Project updated successfully.');

    $this->redirect(
        route('clients.show', $this->project->client_id),
        navigate: true
    );
}
```

`auth()->user()->notify()` — the `Notifiable` trait adds this method to User. Pass the Notification instance and Laravel handles the rest — storing in the database, queuing the email.

---

## Step 5 — Build the Notification Bell Component

Generate the Livewire component:

```bash
php artisan make:livewire NotificationBell --class
```

Open `app/Livewire/NotificationBell.php`:

```php
<?php

namespace App\Livewire;

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function toggleOpen(): void
    {
        $this->open = ! $this->open;

        // Mark all as read when the panel opens
        if ($this->open) {
            auth()->user()
                ->unreadNotifications
                ->markAsRead();
        }
    }

    public function dismiss(string $notificationId): void
    {
        $notification = auth()->user()
            ->notifications()
            ->find($notificationId);

        $notification?->delete();
    }

    public function clearAll(): void
    {
        auth()->user()->notifications()->delete();
        $this->open = false;
    }

    public function render()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->limit(15)
            ->get();

        $unreadCount = auth()->user()
            ->unreadNotifications()
            ->count();

        return view('livewire.notification-bell', compact('notifications', 'unreadCount'));
    }
}
```

Create `resources/views/livewire/notification-bell.blade.php`:

```blade
<div class="relative" x-data>

    {{-- Bell button with unread badge --}}
    <button
        wire:click="toggleOpen"
        class="relative p-2 text-gray-500 hover:text-gray-700 transition-colors"
        aria-label="Notifications"
    >
        {{-- Bell icon --}}
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        {{-- Unread count badge --}}
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white
                         text-xs font-bold rounded-full flex items-center justify-center">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Notification dropdown panel --}}
    @if ($open)
        <div
            class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50"
            wire:click.outside="$set('open', false)"
        >
            {{-- Panel header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                @if ($notifications->isNotEmpty())
                    <button
                        wire:click="clearAll"
                        class="text-xs text-gray-400 hover:text-gray-600"
                    >
                        Clear all
                    </button>
                @endif
            </div>

            {{-- Notification list --}}
            <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                @forelse ($notifications as $notification)
                    @php $data = $notification->data; @endphp
                    <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors
                                {{ is_null($notification->read_at) ? 'bg-indigo-50/40' : '' }}">

                        {{-- Status indicator dot --}}
                        <div class="shrink-0 mt-1">
                            @if (is_null($notification->read_at))
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                            @else
                                <div class="w-2 h-2 rounded-full bg-gray-200"></div>
                            @endif
                        </div>

                        {{-- Notification content --}}
                        <div class="flex-1 min-w-0">
                            <a
                                href="{{ $data['url'] ?? '#' }}"
                                class="text-sm text-gray-900 hover:text-indigo-600 line-clamp-2 leading-snug"
                            >
                                <strong>{{ $data['project_name'] ?? 'A project' }}</strong>
                                status changed to
                                <strong>{{ $data['status_label'] ?? $data['new_status'] }}</strong>
                            </a>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>

                        {{-- Dismiss button --}}
                        <button
                            wire:click="dismiss('{{ $notification->id }}')"
                            class="shrink-0 text-gray-300 hover:text-gray-500 mt-0.5"
                            aria-label="Dismiss"
                        >
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                            </svg>
                        </button>

                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <p class="text-sm text-gray-400">No notifications</p>
                    </div>
                @endforelse
            </div>

        </div>
    @endif

</div>
```

---

## Step 6 — Add the Bell to the Navbar

Open `resources/views/partials/navbar.blade.php` and add the bell component inside the `@auth` block:

```blade
@auth
    <livewire:notification-bell />

    <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="text-sm text-gray-500 hover:text-red-500 transition">
            Log out
        </button>
    </form>
@endauth
```

---

## Step 7 — Access Notifications on the User

The `Notifiable` trait adds these relationships and helpers to the User model:

```php
// All notifications
auth()->user()->notifications;

// Unread only
auth()->user()->unreadNotifications;

// Read only
auth()->user()->readNotifications;

// Mark all as read
auth()->user()->unreadNotifications->markAsRead();

// Mark one as read
$notification->markAsRead();

// Delete a notification
$notification->delete();

// The JSON data
$notification->data['project_name'];

// When it was read (null = unread)
$notification->read_at;
```

---

## Step 8 — Adding More Notification Types

As FreelanceFlow grows, create notifications for each important event. The pattern is always the same:

```bash
php artisan make:notification InvoiceOverdue
php artisan make:notification PaymentReceived
php artisan make:notification ClientAddedProject
```

Each defines `via()`, `toDatabase()`, and optionally `toMail()`. Register the right triggers — use events and listeners from Day 22 to keep the dispatch logic clean.

The `toDatabase()` array is the source of truth for what appears in the notification UI. Always store the URL, the human-readable message text, and any IDs needed to link to the relevant record.

---

## Notification Quick Reference

```php
// Send to a notifiable model
auth()->user()->notify(new ProjectStatusChanged($project, $previousStatus));
$user->notify(new ProjectStatusChanged($project, $previousStatus));

// Send to multiple users
Notification::send($users, new ProjectStatusChanged($project, $previousStatus));

// Send to an on-demand recipient (no model needed)
Notification::route('mail', 'client@example.com')
    ->notify(new ProjectStatusChanged($project, $previousStatus));

// Access user notifications
$user->notifications;             // all — DatabaseNotification collection
$user->unreadNotifications;       // unread only
$user->readNotifications;         // read only
$user->notifications()->count();  // count without loading

// Mark as read
$user->unreadNotifications->markAsRead(); // all at once
$notification->markAsRead();              // one

// Database notification data
$notification->data['project_name'];
$notification->type;        // App\Notifications\ProjectStatusChanged
$notification->read_at;     // null = unread
$notification->created_at;  // Carbon instance

// In tests
Notification::fake();
Notification::assertSentTo($user, ProjectStatusChanged::class);
Notification::assertSentTo($user, ProjectStatusChanged::class, function ($notification) use ($project) {
    return $notification->project->id === $project->id;
});
Notification::assertNotSentTo($user, ProjectStatusChanged::class);
```

---

## What We Learned Today

- **Notifications vs Mailables** — Notifications are for alerts delivered through multiple channels; Mailables are for rich standalone emails like invoices
- **`via()`** — returns the array of channel names. `'database'` and `'mail'` are the most common. Add `'slack'` or `'broadcast'` later
- **`toDatabase()`** — returns a PHP array stored as JSON in the notifications table. Everything needed to render the notification in the UI
- **`toMail()`** — returns a `MailMessage` using the fluent builder. Simpler than a full Mailable for short alert-style emails
- **`Notifiable` trait on User** — adds `notify()`, `notifications()`, `unreadNotifications`, `readNotifications`, `markAsRead()`
- **`ShouldQueue` on Notification** — queues all channels together as one background job
- **`notifications:table` migration** — stores database notifications with UUID, notifiable polymorphic columns, JSON data, and `read_at`
- **Unread badge** — `auth()->user()->unreadNotifications()->count()` gives the number for the bell icon
- **`markAsRead()`** — call on a collection to mark all, or on a single notification to mark one

---

## Day 24 — Building a REST API with Sanctum

Tomorrow FreelanceFlow gets its first API. We will set up Laravel Sanctum for token authentication, create API routes, build JSON Resource classes to shape the API response, protect routes with the `auth:sanctum` middleware, and test the API with a tool like Postman or Insomnia. The API will expose clients and projects — the foundation for a future mobile app or external integrations.

See you on Day 24.