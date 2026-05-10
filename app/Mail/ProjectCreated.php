<?php

namespace App\Mail;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Project $project,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New project: {$this->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.projects.created',
            with: [
                'project'    => $this->project,
                'client'     => $this->project->client,
                'projectUrl' => route('clients.show', $this->project->client_id),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}