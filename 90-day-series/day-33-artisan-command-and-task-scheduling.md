# Day 33 — Artisan Commands & Task Scheduling

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 14 min · **Level:** Intermediate

---

> *"FreelanceFlow sends emails, generates PDFs, and processes payments — but only when a user triggers the action. A real SaaS also does things automatically. Check for overdue invoices every morning. Send payment reminders. Generate monthly revenue reports. Archive old drafts. Today we write custom Artisan commands and schedule them so FreelanceFlow runs maintenance tasks while you sleep."*

---

## What We Are Building Today

1. **`invoice:check-overdue`** — flags overdue invoices and notifies the user
2. **`invoice:send-reminders`** — emails clients with unpaid invoices approaching their due date
3. **`reports:monthly-revenue`** — generates a monthly revenue summary and emails it to the workspace owner
4. **`clients:archive-leads`** — moves stale leads to inactive status
5. **Register all commands** in the scheduler with the right frequency
6. **Set up the cron** — the single entry that powers everything

---

## Step 1 — How Artisan Commands Work

An Artisan command is a PHP class with a `signature`, a `description`, and a `handle()` method. The signature defines the command name and any arguments or options.

```bash
# The commands we are about to build
php artisan invoice:check-overdue
php artisan invoice:send-reminders --days=3
php artisan reports:monthly-revenue --month=2026-04
php artisan clients:archive-leads --days=90
```

---

## Step 2 — Check Overdue Invoices Command

First, create the missing `InvoiceOverdue` notification that the command uses:

```bash
php artisan make:notification InvoiceOverdue
```

Open `app/Notifications/InvoiceOverdue.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'client_id'      => $this->invoice->client_id,
            'client_name'    => $this->invoice->client?->name,
            'total'          => $this->invoice->total,
            'due_at'         => $this->invoice->due_at?->toDateString(),
            'days_overdue'   => $this->invoice->due_at?->diffInDays(now()),
            'url'            => route('invoices.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysOverdue = $this->invoice->due_at?->diffInDays(now());

        return (new MailMessage)
            ->subject("Overdue invoice: {$this->invoice->number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Invoice **{$this->invoice->number}** for **{$this->invoice->client?->name}** is now overdue by {$daysOverdue} day(s).")
            ->line("**Amount due:** {$this->invoice->formatted_total}")
            ->line("**Original due date:** {$this->invoice->due_at?->format('M d, Y')}")
            ->action('View Invoice', route('invoices.index'))
            ->line('You can send the client a payment reminder or update the invoice status from FreelanceFlow.');
    }
}
```

Now create the command:

```bash
php artisan make:command CheckOverdueInvoices
```

Open `app/Console/Commands/CheckOverdueInvoices.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ProjectStatusChanged;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'invoice:check-overdue
                            {--dry-run : List overdue invoices without updating}
                            {--workspace= : Only check a specific workspace ID}';

    protected $description = 'Flag sent invoices past their due date as overdue and notify workspace owners.';

    public function handle(): int
    {
        $this->info('Checking for overdue invoices...');

        $query = Invoice::query()
            ->where('status', 'sent')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->with(['client', 'client.workspace.owner']);

        if ($workspaceId = $this->option('workspace')) {
            $query->whereHas('client', fn ($q) => $q->where('workspace_id', $workspaceId));
        }

        $overdue = $query->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue invoices found.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdue->count()} overdue invoice(s).");

        $bar = $this->output->createProgressBar($overdue->count());
        $bar->start();

        $updated = 0;

        foreach ($overdue as $invoice) {
            if (! $this->option('dry-run')) {
                $invoice->update(['status' => 'overdue']);
                $updated++;

                // Notify the workspace owner
                $owner = $invoice->client->workspace?->owner;
                if ($owner) {
                    $owner->notify(
                        new \App\Notifications\InvoiceOverdue($invoice)
                    );
                }

                Log::info('Invoice marked overdue by scheduler', [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'due_at'         => $invoice->due_at->toDateString(),
                    'days_overdue'   => $invoice->due_at->diffInDays(now()),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn("Dry run: {$overdue->count()} invoices would be marked overdue.");

            $this->table(
                ['Invoice', 'Client', 'Due Date', 'Days Overdue'],
                $overdue->map(fn ($inv) => [
                    $inv->number,
                    $inv->client->name,
                    $inv->due_at->format('M d, Y'),
                    $inv->due_at->diffInDays(now()) . ' days',
                ])
            );
        } else {
            $this->info("Updated {$updated} invoice(s) to overdue status.");
        }

        return self::SUCCESS;
    }
}
```

Test it before scheduling:

```bash
# Dry run first — see what would be affected
php artisan invoice:check-overdue --dry-run

# Then run for real
php artisan invoice:check-overdue

# Specific workspace only
php artisan invoice:check-overdue --workspace=1
```

---

## Step 3 — Send Payment Reminders Command

```bash
php artisan make:command SendInvoiceReminders
```

```php
<?php

namespace App\Console\Commands;

use App\Mail\InvoicePaymentReminder;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoice:send-reminders
                            {--days=3 : Send reminders for invoices due within this many days}
                            {--dry-run : Preview without sending}';

    protected $description = 'Email clients with invoices due within the specified number of days.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Finding invoices due within {$days} day(s)...");

        $invoices = Invoice::query()
            ->where('status', 'sent')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
            ->with(['client'])
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices need reminders today.');
            return self::SUCCESS;
        }

        $this->info("Sending reminders for {$invoices->count()} invoice(s).");

        foreach ($invoices as $invoice) {
            if ($this->option('dry-run')) {
                $this->line("  Would remind: {$invoice->number} → {$invoice->client->email} (due {$invoice->due_at->format('M d')})");
                continue;
            }

            Mail::to($invoice->client->email)
                ->queue(new InvoicePaymentReminder($invoice));

            $this->line("  ✓ Reminder queued for {$invoice->number}");
        }

        return self::SUCCESS;
    }
}
```

Create the Mailable stub:

```bash
php artisan make:mail InvoicePaymentReminder --markdown=emails.invoices.reminder
```

```php
<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Friendly reminder: Invoice {$this->invoice->number} is due soon",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoices.reminder',
            with: [
                'invoice'    => $this->invoice,
                'client'     => $this->invoice->client,
                'paymentUrl' => route('invoices.pay', $this->invoice),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
```

Now create the markdown email template at `resources/views/emails/invoices/reminder.blade.php`:

```blade
<x-mail::message>

# Payment Reminder

Hi {{ $client->name }},

This is a friendly reminder that invoice **{{ $invoice->number }}** is due soon.

<x-mail::panel>
**Invoice:** {{ $invoice->number }}
**Amount due:** {{ $invoice->formatted_total }}
**Due date:** {{ $invoice->due_at->format('F j, Y') }}
</x-mail::panel>

If you have already made payment, please disregard this message.
Otherwise, you can pay securely using the button below.

<x-mail::button :url="$paymentUrl" color="primary">
Pay {{ $invoice->formatted_total }} Now
</x-mail::button>

If you have any questions about this invoice, simply reply to this email.

Thanks,
**FreelanceFlow**

</x-mail::message>
```

---

## Step 4 — Monthly Revenue Report Command

```bash
php artisan make:command GenerateMonthlyRevenueReport
```

```php
<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyRevenueReport extends Command
{
    protected $signature = 'reports:monthly-revenue
                            {--month= : Month to report on (YYYY-MM, defaults to last month)}
                            {--workspace= : Specific workspace ID, defaults to all}';

    protected $description = 'Generate and email monthly revenue reports to workspace owners.';

    public function handle(): int
    {
        $monthInput = $this->option('month');
        $targetDate = $monthInput
            ? Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $this->info("Generating revenue report for: {$targetDate->format('F Y')}");

        $workspaceQuery = Workspace::with('owner');

        if ($workspaceId = $this->option('workspace')) {
            $workspaceQuery->where('id', $workspaceId);
        }

        $workspaces = $workspaceQuery->get();

        foreach ($workspaces as $workspace) {
            $report = $this->buildReport($workspace, $targetDate);

            $this->line("  Workspace: {$workspace->name}");
            $this->line("    Revenue:  ₹" . number_format($report['total_revenue'], 2));
            $this->line("    Invoices: {$report['invoices_paid']} paid, {$report['invoices_outstanding']} outstanding");

            // Email the report to the workspace owner
            if ($workspace->owner?->email) {
                \Illuminate\Support\Facades\Mail::to($workspace->owner->email)
                    ->queue(new \App\Mail\MonthlyRevenueReport($workspace, $report, $targetDate));

                $this->line("    ✓ Report emailed to {$workspace->owner->email}");
            }
        }

        $this->info('Monthly revenue reports generated.');

        return self::SUCCESS;
    }

    private function buildReport(Workspace $workspace, Carbon $date): array
    {
        $paid = Invoice::where('workspace_id', $workspace->id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $date->month)
            ->whereYear('paid_at', $date->year)
            ->get();

        $outstanding = Invoice::where('workspace_id', $workspace->id)
            ->whereIn('status', ['sent', 'overdue'])
            ->get();

        return [
            'total_revenue'         => $paid->sum('total'),
            'invoices_paid'         => $paid->count(),
            'invoices_outstanding'  => $outstanding->count(),
            'outstanding_amount'    => $outstanding->sum('total'),
            'top_clients'           => $paid->groupBy('client_id')
                ->map(fn ($inv) => $inv->sum('total'))
                ->sortDesc()
                ->take(5)
                ->toArray(),
        ];
    }
}
```

Create the `MonthlyRevenueReport` Mailable:

```bash
php artisan make:mail MonthlyRevenueReport --markdown=emails.reports.monthly-revenue
```

Open `app/Mail/MonthlyRevenueReport.php`:

```php
<?php

namespace App\Mail;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MonthlyRevenueReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly array     $report,
        public readonly Carbon    $month,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Monthly Revenue Report — {$this->month->format('F Y')} — {$this->workspace->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reports.monthly-revenue',
            with: [
                'workspace' => $this->workspace,
                'report'    => $this->report,
                'month'     => $this->month,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
```

Create the template at `resources/views/emails/reports/monthly-revenue.blade.php`:

```blade
<x-mail::message>

# Monthly Revenue Report
## {{ $workspace->name }} · {{ $month->format('F Y') }}

Here is your revenue summary for {{ $month->format('F Y') }}.

<x-mail::panel>
**Total revenue collected:** ₹{{ number_format($report['total_revenue'], 2) }}
**Invoices paid:** {{ $report['invoices_paid'] }}
**Outstanding invoices:** {{ $report['invoices_outstanding'] }}
**Outstanding amount:** ₹{{ number_format($report['outstanding_amount'], 2) }}
</x-mail::panel>

<x-mail::button :url="config('app.url') . '/dashboard'" color="primary">
View Dashboard
</x-mail::button>

Thanks,
**FreelanceFlow**

</x-mail::message>
```

---

## Step 5 — Archive Stale Leads Command

```bash
php artisan make:command ArchiveStaleLeads
```

```php
<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class ArchiveStaleLeads extends Command
{
    protected $signature = 'clients:archive-leads
                            {--days=90 : Archive leads that have not been updated in this many days}
                            {--dry-run : Preview without archiving}';

    protected $description = 'Move stale lead clients to inactive status.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $staleLeads = Client::leads()
            ->where('updated_at', '<', now()->subDays($days))
            ->get();

        if ($staleLeads->isEmpty()) {
            $this->info("No stale leads found (inactive for more than {$days} days).");
            return self::SUCCESS;
        }

        $this->info("Found {$staleLeads->count()} stale lead(s).");

        if ($this->option('dry-run')) {
            $this->table(
                ['Name', 'Email', 'Last Updated'],
                $staleLeads->map(fn ($c) => [
                    $c->name,
                    $c->email,
                    $c->updated_at->diffForHumans(),
                ])
            );
            return self::SUCCESS;
        }

        $staleLeads->each->update(['status' => 'inactive']);

        $this->info("Archived {$staleLeads->count()} lead(s) to inactive.");

        return self::SUCCESS;
    }
}
```

---

## Step 6 — Register Commands in the Scheduler

In the current Laravel application structure, scheduling lives in `routes/console.php`:

```php
<?php

use Illuminate\Support\Facades\Schedule;

// Check overdue invoices every morning at 7am
Schedule::command('invoice:check-overdue')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('invoice:check-overdue scheduler failed');
    })
    ->emailOutputOnFailure(config('mail.from.address'));

// Send payment reminders at 9am — invoices due in 3 days
Schedule::command('invoice:send-reminders --days=3')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Also remind for invoices due tomorrow
Schedule::command('invoice:send-reminders --days=1')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Monthly revenue report — 1st of every month at 8am
Schedule::command('reports:monthly-revenue')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping();

// Archive stale leads — every Sunday at midnight
Schedule::command('clients:archive-leads --days=90')
    ->weekly()
    ->sundays()
    ->at('00:00')
    ->withoutOverlapping();

// Clean up Livewire temporary uploads daily
Schedule::command('livewire:clean-uploads')
    ->daily();

// Prune stale queue jobs older than 48 hours
Schedule::command('queue:prune-failed --hours=48')
    ->daily();
```

---

## Step 7 — Set Up the Cron Entry

All scheduled commands run from a single cron entry. Add this to your server's crontab with `crontab -e`:

```bash
* * * * * cd /path/to/freelance-flow && php artisan schedule:run >> /dev/null 2>&1
```

This runs every minute. Laravel's scheduler checks which commands are due to run at that moment — not every command runs every minute, just the single cron entry does.

In local development, use the schedule worker instead:

```bash
# Runs the scheduler continuously without a cron entry
php artisan schedule:work
```

---

## Step 8 — Useful Scheduler Options

```php
// Frequency
->everyMinute()
->everyFiveMinutes()
->hourly()
->hourlyAt(30)          // at 30 minutes past each hour
->daily()
->dailyAt('07:00')
->twiceDaily(9, 17)     // 9am and 5pm
->weekly()
->weeklyOn(1, '08:00')  // Monday at 8am (1=Monday, 0=Sunday)
->monthly()
->monthlyOn(1, '08:00') // 1st of month at 8am
->quarterly()
->yearly()

// Constraints
->weekdays()            // Monday to Friday only
->weekends()
->between('9:00', '17:00')
->when(fn () => now()->isWeekday())

// Reliability
->withoutOverlapping()              // skip if previous run still going
->runInBackground()                 // do not block other scheduled tasks
->onOneServer()                     // only run on one server in a cluster
->onFailure(fn () => /* alert */)
->emailOutputOnFailure('admin@app')
->sendOutputTo('/tmp/command.log')
->appendOutputTo('/tmp/command.log')
```

---

## Artisan Command Tips

```php
// Output methods
$this->info('Green text — success');
$this->warn('Yellow text — warning');
$this->error('Red text — error');
$this->line('Plain text');
$this->newLine();               // blank line
$this->newLine(2);              // two blank lines

// Interactive input
$name = $this->ask('What is the client name?');
$confirmed = $this->confirm('Are you sure?');
$choice = $this->choice('Which status?', ['active', 'inactive', 'lead'], 0);

// Progress bar
$bar = $this->output->createProgressBar(count($items));
$bar->start();
foreach ($items as $item) {
    // process...
    $bar->advance();
}
$bar->finish();

// Table output
$this->table(['Column 1', 'Column 2'], [['A', 'B'], ['C', 'D']]);

// Exit codes
return self::SUCCESS;  // 0
return self::FAILURE;  // 1

// Call other commands from within a command
$this->call('cache:clear');
$this->callSilently('queue:restart');

// Get options and arguments
$this->option('dry-run');  // --dry-run flag
$this->argument('id');     // {id} argument
```

---

## What We Learned Today

- **`php artisan make:command Name`** — generates a command class with `$signature`, `$description`, and `handle()`
- **`$signature` with options** — `{--dry-run}` for boolean flags, `{--days=3}` for options with defaults, `{id}` for required arguments
- **`--dry-run` pattern** — always build a preview mode into destructive or batch commands before running for real
- **`withoutOverlapping()`** — prevents a second instance from running if the first has not finished. Essential for commands that can be slow
- **`routes/console.php`** — where all scheduled commands live in this app. One file, all schedules visible at a glance
- **Single cron entry** — one `* * * * * php artisan schedule:run` drives everything. Laravel's scheduler decides what runs when
- **`schedule:work`** — runs the scheduler locally without a cron entry. Use this in development alongside `queue:work`
- **`onFailure()`** — callback that runs if the command throws an exception. Use it to alert the team
- **`self::SUCCESS` and `self::FAILURE`** — return these from `handle()`. Exit codes signal success or failure to cron and CI systems

---

## Day 34 — Repository Pattern

Tomorrow we introduce the Repository pattern to FreelanceFlow. Right now Service classes call Eloquent directly — `Client::query()`, `Invoice::paid()->sum()`. A Repository layer abstracts the data access behind an interface, making every data query swappable and every service method testable without a database. We will build a `ClientRepository`, an `InvoiceRepository`, and bind them through the service container.

See you on Day 34.
