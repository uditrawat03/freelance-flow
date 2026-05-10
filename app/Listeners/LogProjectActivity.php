<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use Illuminate\Support\Facades\Log;

class LogProjectActivity
{
    public function handle(ProjectCreated $event): void
    {
        Log::info('Project created', [
            'project_id'   => $event->project->id,
            'project_name' => $event->project->name,
            'client_id'    => $event->project->client_id,
            'status'       => $event->project->status,
            'created_by'   => auth()->id(),
        ]);
    }
}