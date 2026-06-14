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

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Invoice $invoice,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'client_id' => $this->invoice->client_id,
            'client_name' => $this->invoice->client?->name,
            'total' => $this->invoice->total,
            'due_at' => $this->invoice->due_at?->toDateString(),
            'days_overdue' => $this->invoice->due_at?->diffInDays(now()),
            'url' => route('invoices.index'),
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
