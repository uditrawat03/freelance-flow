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
            $query->whereHas('client', fn($q) => $q->where('workspace_id', $workspaceId));
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
            if (!$this->option('dry-run')) {
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
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'due_at' => $invoice->due_at->toDateString(),
                    'days_overdue' => $invoice->due_at->diffInDays(now()),
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
                $overdue->map(fn($inv) => [
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