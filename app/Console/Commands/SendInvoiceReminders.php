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
                $this->line("  Would remind: {$invoice->number} -> {$invoice->client->email} (due {$invoice->due_at->format('M d')})");

                continue;
            }

            Mail::to($invoice->client->email)
                ->queue(new InvoicePaymentReminder($invoice));

            $this->line("  OK Reminder queued for {$invoice->number}");
        }

        return self::SUCCESS;
    }
}
