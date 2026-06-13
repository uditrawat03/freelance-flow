<?php

namespace Tests\Feature;

use App\Events\ProjectCreated;
use App\Jobs\SendProjectNotification as SendProjectNotificationJob;
use App\Listeners\SendProjectNotification;
use App\Mail\ProjectCreated as ProjectCreatedMail;
use App\Models\Client;
use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class ProjectCreationTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_creating_a_project_fires_project_created_event_once(): void
    {
        Event::fake([ProjectCreated::class]);

        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $project = app(ProjectService::class)->create([
            'client_id' => $client->id,
            'name' => 'Test Project',
            'status' => 'draft',
            'workspace_id' => $this->workspace->id,
        ]);

        Event::assertDispatched(ProjectCreated::class, fn ($event) => $event->project->is($project));
        Event::assertDispatchedTimes(ProjectCreated::class, 1);
    }

    public function test_project_created_listener_dispatches_notification_job(): void
    {
        Queue::fake();

        $client = Client::factory()->create([
            'email' => 'client@example.com',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]));

        app(SendProjectNotification::class)->handle(new ProjectCreated($project));

        Queue::assertPushed(SendProjectNotificationJob::class, fn ($job) => $job->project->is($project));
    }

    public function test_project_notification_job_sends_email_to_client(): void
    {
        Mail::fake();

        $client = Client::factory()->create([
            'email' => 'client@example.com',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]));

        app()->call([new SendProjectNotificationJob($project), 'handle']);

        Mail::assertSent(ProjectCreatedMail::class, fn ($mail) => $mail->hasTo($client->email));
    }

    public function test_project_status_change_sends_notification_to_user(): void
    {
        Notification::fake();

        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'client_id' => $client->id,
            'status' => 'draft',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]));

        app(ProjectService::class)->update($project, [
            'name' => $project->name,
            'status' => 'active',
        ]);

        Notification::assertSentTo(
            $this->user,
            ProjectStatusChanged::class,
            fn ($notification) => $notification->project->is($project)
                && $notification->previousStatus === 'draft',
        );
    }
}
