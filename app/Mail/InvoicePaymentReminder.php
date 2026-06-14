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
    ) {
        $this->onQueue('emails');
    }

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
                'invoice' => $this->invoice,
                'client' => $this->invoice->client,
                'paymentUrl' => route('invoices.pay', $this->invoice),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
