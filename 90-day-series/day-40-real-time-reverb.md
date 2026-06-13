# Day 40 — Real-Time with Laravel Reverb

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 3 — Advanced**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"FreelanceFlow's dashboard refreshes when you reload the page. That was acceptable in 2015. Today we expect numbers to update while you watch. When a client pays an invoice, the revenue total should tick up. When a project goes overdue, the alert should appear. Today we add WebSockets to FreelanceFlow using Laravel Reverb — the official Laravel WebSocket server — and push real-time events to the dashboard without a single page reload."*

---

## What We Are Building Today

1. **Install Laravel Reverb** — the official Laravel WebSocket server
2. **Configure broadcasting** — channels, authentication, events
3. **A `ProjectStatusUpdated` broadcast event** — fires when a project status changes
4. **A private workspace channel** — scoped to the current workspace
5. **Frontend: Laravel Echo + Reverb** — listen for events in the browser
6. **Update the Dashboard Livewire component** — react to broadcast events
7. **Run Reverb** — the WebSocket server alongside the queue worker

---

## Step 1 — Install Laravel Reverb

```bash
php artisan install:broadcasting
```

This command:
- Installs `laravel/reverb` via Composer
- Publishes the Reverb config to `config/reverb.php`
- Creates `resources/js/echo.js` — the Echo client setup
- Adds `REVERB_*` variables to `.env`
- Updates `config/broadcasting.php` to use Reverb

Then install the JavaScript dependencies:

```bash
npm install --save-dev laravel-echo pusher-js
npm run dev
```

---

## Step 2 — Configure Broadcasting

Update `.env` with Reverb settings:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=freelanceflow
REVERB_APP_KEY=freelanceflow-key
REVERB_APP_SECRET=freelanceflow-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend Reverb connection (used in Echo.js)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Open `config/reverb.php` and verify:

```php
return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [

        'reverb' => [
            'host'            => env('REVERB_HOST', '0.0.0.0'),
            'port'            => env('REVERB_PORT', 8080),
            'hostname'        => env('REVERB_HOSTNAME'),
            'options'         => [
                'tls' => [],
            ],
            'max_request_size'    => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling'             => [
                'enabled'    => env('REVERB_SCALING_ENABLED', false),
                'channel'    => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server'     => [
                    'url'      => env('REDIS_URL'),
                    'host'     => env('REDIS_HOST', '127.0.0.1'),
                    'port'     => env('REDIS_PORT', '6379'),
                    'database' => env('REVERB_SCALING_DATABASE', '2'),
                ],
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
        ],

    ],

    'apps' => [

        'providers' => [
            Illuminate\Broadcasting\Broadcasters\ReverbBroadcaster::class,
        ],

        'apps' => [
            [
                'key'                    => env('REVERB_APP_KEY'),
                'secret'                 => env('REVERB_APP_SECRET'),
                'app_id'                 => env('REVERB_APP_ID'),
                'options'                => [
                    'host'   => env('REVERB_HOST'),
                    'port'   => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                'allowed_origins'        => ['*'],
                'ping_interval'          => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout'       => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_message_size'       => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],
    ],
];
```

---

## Step 3 — Configure the Echo Client

Open `resources/js/echo.js` (created by `install:broadcasting`):

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster:     'reverb',
    key:             import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:          import.meta.env.VITE_REVERB_HOST,
    wsPort:          import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort:         import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS:        (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Import it in `resources/js/app.js`:

```js
import './bootstrap';
import './echo';
```

---

## Step 4 — Define the Workspace Channel

Broadcasting channels define who can subscribe to what. Open `routes/channels.php`:

```php
<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The channel authorization callbacks determine
| if an authenticated user can listen to a given channel.
|
*/

// Private workspace channel — only workspace members can subscribe
Broadcast::channel('workspace.{workspaceId}', function ($user, int $workspaceId) {
    $workspace = Workspace::find($workspaceId);

    return $workspace && $workspace->hasUser($user);
});

// Private user channel — for personal notifications
Broadcast::channel('App.Models.User.{id}', function ($user, int $id) {
    return (int) $user->id === $id;
});
```

The callback returns `true` to allow, `false` to deny. The `workspaceId` segment in the channel name is extracted automatically from the channel name.

---

## Step 5 — Create the ProjectStatusUpdated Broadcast Event

```bash
php artisan make:event ProjectStatusUpdated
```

Open `app/Events/ProjectStatusUpdated.php`:

```php
<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly string  $previousStatus,
    ) {}

    /**
     * The channels to broadcast on.
     * Use a private workspace channel so only workspace members receive it.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->project->workspace_id}"),
        ];
    }

    /**
     * The event name on the frontend.
     * Default would be App\\Events\\ProjectStatusUpdated — too verbose.
     */
    public function broadcastAs(): string
    {
        return 'project.status.updated';
    }

    /**
     * The data sent to the frontend.
     * Keep it lean — only what the frontend needs to update the UI.
     */
    public function broadcastWith(): array
    {
        return [
            'project_id'      => $this->project->id,
            'project_name'    => $this->project->name,
            'status'          => $this->project->status,
            'status_label'    => $this->project->status_label,
            'previous_status' => $this->previousStatus,
            'client_id'       => $this->project->client_id,
            'is_overdue'      => $this->project->is_overdue,
        ];
    }
}
```

**`ShouldBroadcast`** — marks this event for broadcasting. Laravel sends it to Reverb automatically when dispatched.

**`broadcastAs()`** — the event name the frontend listens for. Without this, Echo would listen for `ProjectStatusUpdated` but the default is namespaced: `App\\Events\\ProjectStatusUpdated`.

**`broadcastWith()`** — controls exactly what data is sent. Never broadcast an entire Eloquent model — send only what the UI needs.

---

## Step 6 — Dispatch the Event from ProjectService

Update `app/Services/ProjectService.php` to dispatch the broadcast event when a project status changes:

```php
use App\Events\ProjectStatusUpdated;

public function update(Project $project, array $data, array $tagIds = []): Project
{
    $previousStatus = $project->status;

    $updated = $this->projects->update($project, $data);
    $updated->tags()->sync($tagIds);

    if ($previousStatus !== $updated->status) {
        $updated->loadMissing('client');

        // Notify the user via Laravel Notifications (Day 23)
        auth()->user()->notify(
            new \App\Notifications\ProjectStatusChanged($updated, $previousStatus)
        );

        // Broadcast to all workspace members via WebSocket
        ProjectStatusUpdated::dispatch($updated, $previousStatus);
    }

    return $updated;
}
```

---

## Step 7 — Update the Dashboard to Listen for Events

Update the Dashboard Livewire component to refresh stats when a broadcast event arrives:

```php
// app/Livewire/Dashboard.php
use Livewire\Attributes\On;

class Dashboard extends Component
{
    public string $period = '12months';

    // Listen for the broadcast event dispatched from the frontend via Echo
    #[On('echo-private:workspace.{workspaceId},project.status.updated')]
    public function handleProjectStatusUpdated(array $event): void
    {
        // Bust the cache so the next render fetches fresh data
        app(\App\Services\DashboardService::class)->bustCache();

        // Dispatch a browser notification
        $this->dispatch('notify',
            message: "Project \"{$event['project_name']}\" status changed to {$event['status_label']}.",
            type: 'info'
        );
    }

    public function getWorkspaceIdProperty(): int|null
    {
        return auth()->user()->currentWorkspace()?->id;
    }

    public function render(DashboardService $dashboardService)
    {
        $months = match($this->period) {
            '3months'  => 3,
            '6months'  => 6,
            default    => 12,
        };

        return view('livewire.dashboard', [
            'stats'             => $dashboardService->stats(),
            'revenueChart'      => $dashboardService->revenueChart($months),
            'projectStatusData' => $dashboardService->projectStatusBreakdown(),
            'recent'            => $dashboardService->recentActivity(),
            'overdue'           => $dashboardService->overdueItems(),
            'workspaceId'       => $this->workspaceId,
        ]);
    }
}
```

The `#[On('echo-private:workspace.{workspaceId},project.status.updated')]` attribute is Livewire's bridge between Echo and PHP. When Echo receives the `project.status.updated` event on the private workspace channel, it calls this method automatically — no JavaScript needed.

Pass `workspaceId` to the view so Livewire can interpolate it into the channel name:

```blade
{{-- resources/views/livewire/dashboard.blade.php --}}
<div
    x-data
    x-init="
        window.Echo.private('workspace.{{ $workspaceId }}')
            .listen('.project.status.updated', (event) => {
                $wire.handleProjectStatusUpdated(event);
            });
    "
>
    {{-- ... rest of dashboard --}}
</div>
```

Note the dot prefix (`.project.status.updated`) in the Echo listen call — this is how Echo handles custom `broadcastAs()` names.

---

## Step 8 — Create a Dashboard Stats Broadcast Event

When an invoice is paid, push updated revenue stats to the dashboard:

```bash
php artisan make:event DashboardStatsUpdated
```

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $workspaceId,
        public readonly array $stats,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->workspaceId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.stats.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_id'   => $this->workspaceId,
            'stats'          => $this->stats,
        ];
    }
}
```

Dispatch it from the `InvoiceObserver` when an invoice is marked paid:

```php
// app/Observers/InvoiceObserver.php
use App\Events\DashboardStatsUpdated;
use App\Services\DashboardService;

public function updated(Invoice $invoice): void
{
    $this->bustCache($invoice);

    // Broadcast updated stats when invoice is paid
    if ($invoice->wasChanged('status') && $invoice->status === 'paid') {
        $workspaceId = $invoice->workspace_id;

        // Get fresh stats and broadcast them
        $stats = app(DashboardService::class)->stats();
        DashboardStatsUpdated::dispatch($workspaceId, $stats);
    }
}
```

Listen for it in the dashboard view:

```blade
x-init="
    window.Echo.private('workspace.{{ $workspaceId }}')
        .listen('.project.status.updated', (event) => {
            $wire.handleProjectStatusUpdated(event);
        })
        .listen('.dashboard.stats.updated', (event) => {
            $wire.$refresh();
        });
"
```

`$wire.$refresh()` tells Livewire to re-render the component — the `render()` method runs again, fetching the now-warm cache that was just updated by the observer.

---

## Step 9 — Run Everything Together

Open three terminal tabs for development:

```bash
# Terminal 1 — Laravel development server
php artisan serve

# Terminal 2 — Vite asset watcher
npm run dev

# Terminal 3 — Reverb WebSocket server
php artisan reverb:start --debug

# Terminal 4 — Queue worker (processes broadcast jobs)
php artisan queue:work
```

The `--debug` flag on Reverb prints every connection and event in real time — essential for debugging during development.

Now open two browser tabs both logged in to FreelanceFlow. In one tab go to the dashboard. In another tab edit a project and change its status. Watch the dashboard tab — within milliseconds the notification appears and the stats refresh. No reload.

---

## Broadcasting Quick Reference

```php
// Broadcast an event
ProjectStatusUpdated::dispatch($project, $previousStatus);

// Or explicitly broadcast
broadcast(new ProjectStatusUpdated($project, $previousStatus));

// Broadcast on queue (recommended for production)
broadcast(new ProjectStatusUpdated($project, $previousStatus))->toOthers();
// toOthers() — send to all connections EXCEPT the one that triggered it
// prevents the user who made the change from seeing a duplicate notification

// Channel types
new Channel('public-channel')           // anyone can subscribe
new PrivateChannel('private-channel')   // requires auth callback in channels.php
new PresenceChannel('presence-channel') // shows who is online

// Echo.js — subscribe
Echo.channel('public-channel').listen('EventName', callback)
Echo.private('private-channel').listen('.event.name', callback)  // dot prefix for custom broadcastAs

// Livewire — listen for broadcast events
#[On('echo:channel-name,EventName')]
public function handle(array $event): void {}

#[On('echo-private:channel-name,event.name')]
public function handle(array $event): void {}
```

---

## What We Learned Today

- **Laravel Reverb** — `php artisan install:broadcasting` sets up the entire WebSocket stack. Reverb is a native PHP WebSocket server, no Node.js required
- **`ShouldBroadcast`** — add this interface to any event to broadcast it through Reverb when dispatched
- **`broadcastAs()`** — sets the frontend event name. Without it Echo would need the full PHP class name with backslashes
- **`broadcastWith()`** — controls exactly what data reaches the browser. Keep it lean — only what the UI needs
- **Private channels** — `new PrivateChannel('workspace.{id}')`. Requires an auth callback in `routes/channels.php`. Only authenticated workspace members can subscribe
- **`#[On('echo-private:workspace.{workspaceId},event.name')]`** — Livewire's bridge between Echo and PHP. When Echo receives the event, Livewire calls the method automatically
- **`$wire.$refresh()`** — tells Livewire to re-render the component from JavaScript. Use when a broadcast event means the server-side data has changed
- **`toOthers()`** — broadcast to all connections except the sender. Prevents the user who triggered the event from seeing a duplicate notification
- **`php artisan reverb:start --debug`** — runs the Reverb WebSocket server in debug mode. Essential for development

---

## Day 41 — Feature Testing with PHPUnit

Tomorrow we write the tests that should have been written all along. We will set up the testing environment with an in-memory SQLite database, write feature tests for client CRUD, test the API endpoints with `actingAs()` and Sanctum token auth, use `Mail::fake()` and `Queue::fake()` to assert side effects without actually sending emails, and build a test helper trait that seeds a workspace and demo user for every test.

See you on Day 41.