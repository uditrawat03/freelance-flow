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