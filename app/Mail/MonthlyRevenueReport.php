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