<?php

namespace Tests\Feature;

use App\Events\ProjectCreated;
use App\Listeners\NotifyTeamOnSlack;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class SlackNotificationTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
        config(['services.slack.webhook' => 'https://hooks.slack.com/services/test/webhook']);
    }

    public function test_slack_notification_is_sent_when_project_is_created(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $project = $this->projectWithoutEvents('Client Portal');

        app(NotifyTeamOnSlack::class)->handle(new ProjectCreated($project));

        Http::assertSent(function ($request) use ($project) {
            return $request->url() === config('services.slack.webhook')
                && $request->method() === 'POST'
                && str_contains($request['text'], $project->name)
                && str_contains($request['text'], $project->client->name);
        });
        Http::assertSentCount(1);
    }

    public function test_http_fake_returns_specific_and_fallback_responses(): void
    {
        Http::fake([
            'api.example.com/clients' => Http::response([
                'clients' => [
                    ['id' => 1, 'name' => 'Test Client'],
                ],
            ], 200),
            'api.example.com/*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $clients = Http::get('https://api.example.com/clients');
        $fallback = Http::get('https://api.example.com/projects');

        $this->assertTrue($clients->ok());
        $this->assertSame('Test Client', $clients->json('clients.0.name'));
        $this->assertTrue($fallback->notFound());
        Http::assertSentCount(2);
    }

    public function test_http_fake_can_return_response_sequences(): void
    {
        Http::fake([
            'api.example.com/jobs/*' => Http::sequence()
                ->push(['status' => 'processing'], 202)
                ->push(['status' => 'complete'], 200)
                ->push(['status' => 'failed'], 500),
        ]);

        $this->assertSame('processing', Http::get('https://api.example.com/jobs/1')->json('status'));
        $this->assertSame('complete', Http::get('https://api.example.com/jobs/1')->json('status'));
        $this->assertTrue(Http::get('https://api.example.com/jobs/1')->serverError());
    }

    public function test_http_fake_can_simulate_server_failures(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([], 500),
        ]);

        $response = Http::get('https://api.example.com/anything');

        $this->assertTrue($response->serverError());
        $this->assertSame(500, $response->status());
    }

    public function test_http_fake_can_simulate_connection_failures(): void
    {
        Http::fake([
            'api.example.com/*' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        Http::get('https://api.example.com/unavailable');
    }

    public function test_prevent_stray_requests_blocks_unmocked_urls(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.example.com/allowed' => Http::response(['ok' => true], 200),
        ]);

        $this->assertTrue(Http::get('https://api.example.com/allowed')->ok());

        $this->expectException(StrayRequestException::class);

        Http::get('https://api.example.com/not-faked');
    }

    public function test_assert_nothing_sent_when_code_path_skips_http(): void
    {
        Http::fake();

        Http::assertNothingSent();
    }

    private function projectWithoutEvents(string $name): Project
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        return Project::withoutEvents(fn () => Project::factory()->create([
            'client_id' => $client->id,
            'name' => $name,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]));
    }
}
