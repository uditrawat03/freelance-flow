<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Project $project,
        public readonly string $previousStatus,
    ) {
    }

    // Which channels to deliver through
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    // --- Database channel ---
    // Stored as JSON in the notifications table
    public function toDatabase(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'client_id' => $this->project->client_id,
            'client_name' => $this->project->client?->name,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->project->status,
            'status_label' => $this->project->status_label,
            'url' => route('clients.show', $this->project->client_id),
        ];
    }

    // --- Mail channel ---
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Project update: {$this->project->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("The status of **{$this->project->name}** has been updated.")
            ->line("**Previous status:** " . ucfirst(str_replace('_', ' ', $this->previousStatus)))
            ->line("**New status:** {$this->project->status_label}")
            ->action('View Project', route('clients.show', $this->project->client_id))
            ->line('You are receiving this because you manage this project on FreelanceFlow.');
    }

    // Called if all retries fail
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('ProjectStatusChanged notification failed', [
            'project_id' => $this->project->id,
            'error' => $exception->getMessage(),
        ]);
    }
}