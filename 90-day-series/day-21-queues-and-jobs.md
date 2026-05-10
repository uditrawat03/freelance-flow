# Day 21 — Queues & Jobs — Emails in the Background

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"Yesterday's email send blocks the entire request. If the mail server takes 2 seconds to respond, the user waits 2 seconds staring at a spinner. In production that becomes 5 seconds, then a timeout, then an error. Queues solve this permanently. Hand the email to a background worker, return the response immediately, and let the worker deal with the mail server on its own time."*

---

## The Problem with Synchronous Email

When `Mail::send()` runs, here is what happens in order:

1. User clicks "Create project"
2. Laravel validates the form
3. Laravel saves the project to the database
4. Laravel connects to the mail server
5. Laravel sends the email
6. The mail server acknowledges receipt
7. Laravel returns the redirect response
8. The user sees the success page

Steps 4–6 are out of your control. A slow mail server, a network hiccup, or a brief Mailgun outage and the user gets an error on a form that saved successfully. The project exists in the database but the user sees a 500 error because the email failed.

Queues decouple email sending from the HTTP request:

1. User clicks "Create project"
2. Laravel validates the form
3. Laravel saves the project to the database
4. Laravel pushes a job onto the queue
5. **Laravel returns the redirect immediately — user sees success**
6. *In the background:* the queue worker picks up the job
7. *In the background:* the email is sent

The user experience is instant. The email still goes out. A mail server failure does not break the form.

---

## What We Are Building Today

1. **Configure the queue driver** — database queue for simplicity
2. **The jobs table** migration — where queued jobs are stored
3. **Convert `Mail::send()` to `Mail::queue()`** — one word change
4. **Build a custom Job class** — `SendProjectNotification`
5. **Failed jobs table** — catching and retrying failed jobs
6. **Run the queue worker** — processing jobs in the background
7. **Queue configuration reference** — Redis, database, sync drivers

---

## Step 1 — Configure the Queue Driver

Open `.env` and set the queue connection to `database`:

```env
QUEUE_CONNECTION=database
```

The `database` driver stores jobs in a MySQL table — no Redis or additional infrastructure needed for development. It is the right choice while learning. We switch to Redis in Phase 4 when we cover scaling.

Other drivers for reference:

```env
QUEUE_CONNECTION=sync      # no queue — runs jobs immediately (default in local)
QUEUE_CONNECTION=database  # stores jobs in MySQL — good for development
QUEUE_CONNECTION=redis     # fastest — requires Redis server (Phase 4)
QUEUE_CONNECTION=sqs       # AWS SQS — production at scale
```

---

## Step 2 — Create the Queue Tables

The database driver needs two tables — one for pending jobs and one for failed jobs.

```bash
# Create the jobs table migration
php artisan queue:table

# Create the failed_jobs table migration
php artisan queue:failed-table

# Run both migrations
php artisan migrate
```

Check your database — you now have:

- `jobs` — pending and processing jobs
- `failed_jobs` — jobs that exhausted all retry attempts

---

## Step 3 — The Quickest Win: Mail::queue()

The simplest way to queue an email is to replace `send()` with `queue()` in the Livewire component. The Mailable already has the `Queueable` trait — that is all it needs.

Open `app/Livewire/Projects/Create.php`:

```php
use App\Mail\ProjectCreated;
use Illuminate\Support\Facades\Mail;

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
    $project->load('client');

    // One word changed: send() → queue()
    // The email is now dispatched to the background
    Mail::to($project->client->email)
        ->queue(new ProjectCreated($project));

    session()->flash('success', 'Project created. Client will be notified shortly.');

    $this->redirect(
        route('clients.show', $this->selectedClientId),
        navigate: true
    );
}
```

Create a project now and watch what happens. The redirect is instant. Open Mailpit — the email is not there yet. Open your database and look at the `jobs` table — there is a new row with the serialised job payload. Run the queue worker and the job processes, the email arrives in Mailpit.

The flash message also changed subtly — "will be notified shortly" instead of "client notified" — because the email has not been sent yet at the point of redirect. Small copy detail, honest user expectation.

---

## Step 4 — Build a Custom Job Class

`Mail::queue()` is convenient but limited — you cannot add custom logic, retry behaviour, or error handling. For anything beyond a simple email send, create a dedicated Job class.

```bash
php artisan make:job SendProjectNotification
```

Open `app/Jobs/SendProjectNotification.php`:

```php
<?php

namespace App\Jobs;

use App\Mail\ProjectCreated;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendProjectNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // How many times to retry before moving to failed_jobs
    public int $tries = 3;

    // Wait (seconds) before retrying after a failure
    public int $backoff = 60;

    // Maximum seconds before the job is considered timed out
    public int $timeout = 30;

    public function __construct(
        public readonly Project $project,
    ) {}

    public function handle(): void
    {
        // Re-fetch relationships — they may not be loaded after deserialisation
        $this->project->loadMissing('client');

        // Guard: do not send if the client has no email
        if (! $this->project->client?->email) {
            Log::warning('SendProjectNotification skipped — client has no email', [
                'project_id' => $this->project->id,
            ]);
            return;
        }

        // Guard: do not send if the project was deleted while queued
        if ($this->project->trashed()) {
            Log::info('SendProjectNotification skipped — project was deleted', [
                'project_id' => $this->project->id,
            ]);
            return;
        }

        Mail::to($this->project->client->email)
            ->send(new ProjectCreated($this->project));

        Log::info('Project notification sent', [
            'project_id' => $this->project->id,
            'client_id'  => $this->project->client_id,
            'to'         => $this->project->client->email,
        ]);
    }

    // Called when the job fails after all retries
    public function failed(\Throwable $exception): void
    {
        Log::error('SendProjectNotification failed permanently', [
            'project_id' => $this->project->id,
            'error'      => $exception->getMessage(),
        ]);

        // Optionally notify the FreelanceFlow team via a different channel
        // NotifyAdminOfFailedJob::dispatch($this->project, $exception->getMessage());
    }
}
```

**Key properties explained:**

- `$tries = 3` — if the job fails (exception thrown), Laravel retries it up to 3 times before moving it to `failed_jobs`
- `$backoff = 60` — wait 60 seconds between retry attempts. Gives a slow mail server time to recover
- `$timeout = 30` — if the job takes longer than 30 seconds, kill it and count it as failed
- `SerializesModels` — stores the project ID in the serialised payload, not the full model. When the job runs, it re-fetches the model fresh from the database. This prevents stale data
- `loadMissing('client')` — re-loads relationships after deserialisation. Never assume relationships are loaded on a queued model
- The soft-delete guard — the project might be deleted between dispatch and processing. Check `trashed()` before sending
- `failed()` — runs when all retry attempts are exhausted. Log it, alert the team, do not silently swallow the error

---

## Step 5 — Dispatch the Job

Update the Livewire `Create` component to dispatch the job instead of queuing the Mailable directly:

```php
use App\Jobs\SendProjectNotification;

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

    // Dispatch the job to the queue
    // Project model is automatically serialised for background processing
    SendProjectNotification::dispatch($project);

    session()->flash('success', 'Project created. Client will be notified shortly.');

    $this->redirect(
        route('clients.show', $this->selectedClientId),
        navigate: true
    );
}
```

You can also dispatch with a delay — useful for "send reminder 24 hours before deadline":

```php
// Dispatch immediately
SendProjectNotification::dispatch($project);

// Dispatch with a 5 minute delay
SendProjectNotification::dispatch($project)->delay(now()->addMinutes(5));

// Dispatch only during business hours (using a specific time)
SendProjectNotification::dispatch($project)->delay(
    now()->setHour(9)->setMinute(0)->addDay()
);

// Dispatch on a specific queue channel
SendProjectNotification::dispatch($project)->onQueue('emails');

// Dispatch synchronously (bypass queue — useful in tests)
SendProjectNotification::dispatchSync($project);
```

---

## Step 6 — Run the Queue Worker

The queue worker is a long-running PHP process that picks jobs off the queue and executes them.

```bash
# Process jobs continuously
php artisan queue:work

# Process jobs and stop after queue is empty (good for cron-based setups)
php artisan queue:work --stop-when-empty

# Process a specific queue channel
php artisan queue:work --queue=emails,default

# Set maximum number of jobs to process before restarting (prevents memory leaks)
php artisan queue:work --max-jobs=100

# Set maximum seconds per job before timeout
php artisan queue:work --timeout=60

# Verbose output — shows each job as it processes
php artisan queue:work -v
```

In local development, open a second terminal tab and run `php artisan queue:work`. Leave it running alongside `npm run dev` and `php artisan serve`. Any queued job dispatched from the browser processes immediately in that terminal.

When you see this output, the job ran:

```
[2026-05-01 10:32:14] Processing: App\Jobs\SendProjectNotification
[2026-05-01 10:32:14] Processed:  App\Jobs\SendProjectNotification
```

---

## Step 7 — Handling Failed Jobs

When a job fails all its retry attempts, it moves to the `failed_jobs` table. List and retry failed jobs with these commands:

```bash
# List all failed jobs
php artisan queue:failed

# Retry a specific failed job by its ID
php artisan queue:retry 5

# Retry all failed jobs
php artisan queue:retry all

# Delete a specific failed job
php artisan queue:forget 5

# Delete all failed jobs
php artisan queue:flush
```

In your Tinker session, inspect failed jobs:

```bash
php artisan tinker

DB::table('failed_jobs')->latest()->first();
// Shows: id, uuid, connection, queue, payload, exception, failed_at
```

The `exception` column contains the full stack trace — exactly what went wrong.

---

## Step 8 — Queue Job for Other FreelanceFlow Features

As Phase 2 progresses, these are the jobs FreelanceFlow will queue. Create stubs now:

```bash
php artisan make:job SendInvoiceEmail
php artisan make:job SendPaymentConfirmation
php artisan make:job GenerateInvoicePdf
php artisan make:job SendProjectStatusNotification
```

Each follows the same pattern as `SendProjectNotification`:

```php
class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function handle(): void
    {
        $this->invoice->loadMissing('client');
        // send the email...
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendInvoiceEmail failed', ['invoice_id' => $this->invoice->id]);
    }
}
```

---

## Queue Reference

```php
// Dispatch methods
SendProjectNotification::dispatch($project);
SendProjectNotification::dispatchSync($project);   // bypass queue
SendProjectNotification::dispatchIf($condition, $project);
SendProjectNotification::dispatchUnless($condition, $project);

// Chaining
SendProjectNotification::dispatch($project)
    ->delay(now()->addMinutes(5))
    ->onQueue('emails')
    ->onConnection('redis');

// Artisan commands
php artisan queue:work              // process jobs continuously
php artisan queue:work --once       // process one job then stop
php artisan queue:listen            // like work but restarts on code changes
php artisan queue:failed            // list failed jobs
php artisan queue:retry all         // retry all failed jobs
php artisan queue:flush             // delete all failed jobs
php artisan queue:clear             // delete all pending jobs
php artisan queue:monitor           // watch queue sizes

// In tests
Queue::fake();
Queue::assertPushed(SendProjectNotification::class);
Queue::assertPushed(SendProjectNotification::class, function ($job) use ($project) {
    return $job->project->id === $project->id;
});
Queue::assertNotPushed(SendProjectNotification::class);
```

---

## What We Learned Today

- **Why queues exist** — decoupling slow operations (email, PDF, SMS) from the HTTP request. The user gets an instant response, the background worker handles the heavy lifting
- **`QUEUE_CONNECTION=database`** — the simplest queue driver. Jobs stored in MySQL. No extra infrastructure needed for development
- **`queue:table` and `queue:failed-table`** — the two migrations needed for the database queue driver
- **`Mail::queue()` vs a custom Job** — `Mail::queue()` is quick, a Job class gives you retries, backoff, timeout, guards, and a `failed()` hook
- **`SerializesModels`** — stores the model ID, not the full model. The model is re-fetched fresh when the job runs. Always call `loadMissing()` inside `handle()`
- **`$tries`, `$backoff`, `$timeout`** — retry configuration on the Job class. Set these on every job that talks to an external service
- **Soft-delete guard** — always check `$model->trashed()` inside jobs. The record might be deleted between dispatch and processing
- **`failed()`** — called when all retries are exhausted. Log it. Never silently swallow job failures
- **`php artisan queue:work`** — the worker process. Run it in a separate terminal locally, as a managed process in production

---

## Day 22 — Events & Listeners

Tomorrow we introduce Laravel's event system. Right now `SendProjectNotification::dispatch()` is called directly inside the Livewire component. That means the component knows about the notification job — coupling that does not belong there. We will create a `ProjectCreated` event and a `SendProjectNotification` listener so the Livewire component simply fires an event and has no idea what happens next.

See you on Day 22.