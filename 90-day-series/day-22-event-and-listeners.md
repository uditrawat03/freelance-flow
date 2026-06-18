# Day 22 — Events & Listeners — Decoupling FreelanceFlow

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 15 min · **Level:** Intermediate

---

> *"The Livewire Create component currently knows about SendProjectNotification. It imports the Job class. It calls dispatch() directly. That is coupling — the form component knows implementation details it should not care about. Today we introduce Laravel events. The component fires one event. Everything else — the email, the log entry, future Slack notifications — listens for it and reacts. The component stays completely unaware of what happens next."*

---

## The Coupling Problem

Look at the current `save()` method in the Create Project Livewire component:

```php
use App\Jobs\SendProjectNotification;

public function save(): void
{
    $project = Project::create([...]);
    $project->tags()->sync($this->selectedTags);

    SendProjectNotification::dispatch($project); // ← knows too much
    
    session()->flash('success', 'Project created.');
    $this->redirect(route('clients.show', $this->selectedClientId), navigate: true);
}
```

The component knows:
- That a notification exists
- That it is a Job class
- That it uses dispatch()
- The name `SendProjectNotification`

Now imagine you later want to:
- Send a Slack message to your team when a project is created
- Log project creation to an analytics service
- Create a default task list for new projects
- Update a dashboard counter

Every one of those means going back into the Livewire component and adding another line. The component grows. The coupling grows. The single responsibility is gone.

The solution: **events**. The component fires one event — `ProjectCreated`. Everything that needs to react to that event registers as a listener and handles itself.

---

## What We Are Building Today

1. A **`ProjectCreated` event class** — carries the project data
2. A **`SendProjectNotification` listener** — handles the queued email
3. A **`LogProjectActivity` listener** — writes to the activity log
4. **Register both listeners** in the EventServiceProvider
5. **Update the Livewire component** to fire the event instead of dispatching the job
6. **Queued listeners** — listeners that run in the background automatically

---

## Step 1 — Create the Event Class

```bash
php artisan make:event ProjectCreated
```

Open `app/Events/ProjectCreated.php`:

```php
<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Project $project,
    ) {}
}
```

That is the entire event class. It is a simple data container — it holds the project and nothing else. The event does not know who is listening or what they will do with the project.

---

## Step 2 — Create the Listeners

### Listener 1: Send the notification email

```bash
php artisan make:listener SendProjectNotification --event=ProjectCreated
```

Open `app/Listeners/SendProjectNotification.php`:

```php
<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use App\Jobs\SendProjectNotification as SendProjectNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendProjectNotification implements ShouldQueue
{
    use InteractsWithQueue;

    // Queue configuration for this listener
    public int $tries   = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function handle(ProjectCreated $event): void
    {
        $project = $event->project;
        $project->loadMissing('client');

        if (! $project->client?->email) {
            return;
        }

        if ($project->trashed()) {
            return;
        }

        // Dispatch the existing job — listener delegates to the job
        SendProjectNotificationJob::dispatch($project);
    }

    public function failed(ProjectCreated $event, \Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('SendProjectNotification listener failed', [
            'project_id' => $event->project->id,
            'error'      => $exception->getMessage(),
        ]);
    }
}
```

Note the naming — the listener is `App\Listeners\SendProjectNotification` and it dispatches `App\Jobs\SendProjectNotification`. The listener handles the event, delegates actual work to the Job. This keeps each class with one job.

### Listener 2: Log project activity

```bash
php artisan make:listener LogProjectActivity --event=ProjectCreated
```

Open `app/Listeners/LogProjectActivity.php`:

```php
<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use Illuminate\Support\Facades\Log;

class LogProjectActivity
{
    public function handle(ProjectCreated $event): void
    {
        Log::info('Project created', [
            'project_id'   => $event->project->id,
            'project_name' => $event->project->name,
            'client_id'    => $event->project->client_id,
            'status'       => $event->project->status,
            'created_by'   => auth()->id(),
        ]);
    }
}
```

This listener runs synchronously — no `ShouldQueue`. Writing a log entry is fast and should happen immediately. Sending an email is slow and goes to the queue. Each listener decides for itself.

---

## Step 3 — Register the Listeners

Open `app/Providers/EventServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Events\ProjectCreated;
use App\Listeners\LogProjectActivity;
use App\Listeners\SendProjectNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ProjectCreated::class => [
            LogProjectActivity::class,       // runs synchronously
            SendProjectNotification::class,  // runs in the queue (ShouldQueue)
        ],
    ];

    public function boot(): void
    {
        //
    }
}
```

Laravel reads the `$listen` array and wires everything up automatically. When `ProjectCreated` is fired, both listeners are called. The order matters — `LogProjectActivity` runs first, then `SendProjectNotification` is queued.

> **Auto-discovery alternative:** In modern Laravel versions, you can skip registering listeners manually by using event discovery or listener attributes where available. The EventServiceProvider `$listen` array approach is shown here because it gives you a single place to see all event-listener mappings in the app — which is more readable as the app grows.

---

## Step 4 — Update the Livewire Component

Open `app/Livewire/Projects/Create.php`. Remove the Job import, add the Event import:

```php
<?php

namespace App\Livewire\Projects;

use App\Events\ProjectCreated;   // ← only this
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('New Project — FreelanceFlow')]
class Create extends Component
{
    use WithFileUploads;

    // ... properties unchanged ...

    public function save(): void
    {
        $this->validate();

        $project = Project::create([
            'client_id'   => $this->selectedClientId,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'budget'      => $this->budget ?: null,
            'deadline'    => $this->deadline ?: null,
        ]);

        $project->tags()->sync($this->selectedTags);

        // Fire the event — the component's job is done
        // It has no idea who listens or what they do
        ProjectCreated::dispatch($project);

        session()->flash('success', 'Project created. Client will be notified shortly.');

        $this->redirect(
            route('clients.show', $this->selectedClientId),
            navigate: true
        );
    }

    // ... render() unchanged ...
}
```

The component now has zero knowledge of email, logging, or any side effect. It creates the project, fires the event, and redirects. That is all it should ever do.

---

## Step 5 — Test the Event Chain

Create a project in the browser. Then verify in your terminal running `php artisan queue:work`:

```
[2026-05-02 09:15:22] Processing: App\Listeners\SendProjectNotification
[2026-05-02 09:15:22] Processed:  App\Listeners\SendProjectNotification
```

Check `storage/logs/laravel.log`:

```
[2026-05-02 09:15:22] local.INFO: Project created
    {"project_id":47,"project_name":"Website Redesign — Acme Corp","client_id":3,"created_by":1}
```

Check Mailpit — the email is there.

Three things happened from one `ProjectCreated::dispatch($project)` call. The component fired one event and walked away.

---

## Step 6 — Adding a New Listener Later

This is where the architecture pays off. Imagine you want to send a Slack message when a project is created. Without events you go back to the Livewire component and add another line. With events:

```bash
php artisan make:listener NotifyTeamOnSlack --event=ProjectCreated
```

```php
class NotifyTeamOnSlack implements ShouldQueue
{
    public function handle(ProjectCreated $event): void
    {
        // Send Slack notification
        Http::post(config('services.slack.webhook'), [
            'text' => "New project: {$event->project->name} for {$event->project->client->name}",
        ]);
    }
}
```

Register it:

```php
ProjectCreated::class => [
    LogProjectActivity::class,
    SendProjectNotification::class,
    NotifyTeamOnSlack::class,  // ← add one line here
],
```

The Livewire component is never touched. The test for the component is never touched. The event just has a new listener.

---

## Step 7 — Fire Events from the Model

A cleaner pattern for large applications: fire the event from the model itself, not from the controller or Livewire component. Eloquent model observers and the `$dispatchesEvents` property handle this automatically.

Add to the `Project` model:

```php
// app/Models/Project.php

use App\Events\ProjectCreated;
use App\Events\ProjectUpdated;
use App\Events\ProjectDeleted;

protected $dispatchesEvents = [
    'created' => ProjectCreated::class,
    'updated' => ProjectUpdated::class,
    'deleted' => ProjectDeleted::class,
];
```

Now `ProjectCreated` fires automatically every time `Project::create()` is called — from the Livewire component, from a seeder, from Tinker, from an API endpoint. Anywhere a project is created, the event fires. No explicit `dispatch()` call needed anywhere.

Remove the `ProjectCreated::dispatch($project)` line from the Livewire component — the model handles it.

```php
// app/Models/Project.php
protected $dispatchesEvents = [
    'created' => \App\Events\ProjectCreated::class,
];
```

```php
// Livewire Create component save() — no dispatch() needed
$project = Project::create([...]);   // ← ProjectCreated fires automatically here
$project->tags()->sync($this->selectedTags);

session()->flash('success', 'Project created. Client will be notified shortly.');
$this->redirect(route('clients.show', $this->selectedClientId), navigate: true);
```

This is the most decoupled approach — the model knows what events it emits, and everything else listens.

---

## Queued vs Synchronous Listeners

```php
// Synchronous listener — runs immediately, blocks the request
class LogProjectActivity
{
    public function handle(ProjectCreated $event): void { ... }
}

// Queued listener — runs in the background via the queue worker
class SendProjectNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ProjectCreated $event): void { ... }
}
```

**Rule of thumb:**

| Operation | Type |
|---|---|
| Write to log | Synchronous |
| Update a database counter | Synchronous |
| Send an email | Queued |
| Send an SMS | Queued |
| Call an external API | Queued |
| Generate a PDF | Queued |
| Send a Slack message | Queued |

If it touches an external service or takes more than a few milliseconds — queue it.

---

## Events & Listeners Reference

```php
// Create
php artisan make:event ProjectCreated
php artisan make:listener SendProjectNotification --event=ProjectCreated

// Fire an event
ProjectCreated::dispatch($project);
event(new ProjectCreated($project));  // equivalent

// Auto-dispatch from model
protected $dispatchesEvents = [
    'created' => ProjectCreated::class,
    'updated' => ProjectUpdated::class,
    'deleted' => ProjectDeleted::class,
];

// Listener handles the event
public function handle(ProjectCreated $event): void
{
    $project = $event->project;
}

// Failed queued listener
public function failed(ProjectCreated $event, \Throwable $exception): void
{
    Log::error('Listener failed', ['project_id' => $event->project->id]);
}

// In tests
Event::fake();
Event::assertDispatched(ProjectCreated::class);
Event::assertDispatched(ProjectCreated::class, function ($event) use ($project) {
    return $event->project->id === $project->id;
});
Event::assertNotDispatched(ProjectCreated::class);

// Fake only specific events (let others fire normally)
Event::fake([ProjectCreated::class]);
```

---

## What We Learned Today

- **Events decouple side effects from the action that causes them** — the Livewire component creates a project and fires one event. Email, logging, Slack, analytics — all listen independently
- **`php artisan make:event` and `make:listener`** — event is a data container, listener is the handler
- **`$listen` in EventServiceProvider** — registers which listeners respond to which events. One place to see the entire event map of the app
- **`ShouldQueue` on a listener** — makes the listener run in the background automatically. No `dispatch()` needed in the listener itself
- **`$dispatchesEvents` on the model** — fires events automatically on Eloquent lifecycle hooks (created, updated, deleted). The cleanest decoupling — no explicit dispatch() anywhere
- **Synchronous vs queued listeners** — fast operations run sync, slow external operations run queued
- **Adding a new listener** — register one line in EventServiceProvider, write the listener class. Zero changes to anything that already works
- **`Event::fake()` and `Event::assertDispatched()`** — test that events were fired without side effects

---

## Day 23 — Notifications System

Tomorrow we add Laravel Notifications — a higher-level API than raw Mail classes. A Notification can deliver the same message through multiple channels simultaneously: database (bell icon in the UI), email, Slack, and SMS. We will build a project status change notification that appears in the FreelanceFlow notification bell and sends an email — all from one Notification class.

See you on Day 23.
