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

    public function handle(Logger $logger): void
    {
        $this->project->loadMissing('client');

        if (! $this->project->client?->email) {
            $logger->warning('SendProjectNotification skipped — client has no email', [
                'project_id' => $this->project->id,
            ]);
            return;
        }

        Mail::to($this->project->client->email)
            ->send(new ProjectCreated($this->project));

        $logger->info('Project notification sent', [
            'project_id' => $this->project->id,
            'to'         => $this->project->client->email,
        ]);
    }

    // Called when the job fails after all retries
    public function failed(\Throwable $exception): void
    {
        Log::error('SendProjectNotification failed permanently', [
            'project_id' => $this->project->id,
            'error'      => $exception->getMessage(),
            'trace'      => $exception->getTraceAsString(),
        ]);

        // Optionally notify the FreelanceFlow team via a different channel
        // NotifyAdminOfFailedJob::dispatch($this->project, $exception->getMessage());
    }
}