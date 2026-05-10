<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

class NotifyTeamOnSlack implements ShouldQueue
{
    public function handle(ProjectCreated $event): void
    {
        // Send Slack notification
        Http::post(config('services.slack.webhook'), [
            'text' => "New project: {$event->project->name} for {$event->project->client->name}",
        ]);
    }
}
