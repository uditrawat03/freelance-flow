<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_can_list_clients_via_api(): void
    {
        Client::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'status', 'status_label', 'projects_count'],
                ],
                'meta' => ['total', 'per_page', 'current_page'],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_clients_by_status(): void
    {
        Client::factory()->count(3)->active()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        Client::factory()->count(2)->lead()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->getJson('/api/v1/clients?status=active')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_client_via_api(): void
    {
        $this->postJson('/api/v1/clients', [
            'name' => 'API Client',
            'email' => 'api@test.com',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Api Client')
            ->assertJsonPath('data.email', 'api@test.com');

        $this->assertDatabaseHas('clients', [
            'email' => 'api@test.com',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_api_returns_422_on_validation_failure(): void
    {
        $this->postJson('/api/v1/clients', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_can_show_a_client_with_projects(): void
    {
        $client = Client::factory()
            ->hasProjects(3, [
                'workspace_id' => $this->workspace->id,
                'user_id' => $this->user->id,
            ])
            ->create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $this->user->id,
            ]);

        $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonCount(3, 'data.projects');
    }

    public function test_can_update_a_client_via_api(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->putJson("/api/v1/clients/{$client->id}", [
            'name' => 'Updated Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_can_delete_a_client_via_api(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson("/api/v1/clients/{$client->id}")
            ->assertOk();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_api_does_not_show_clients_from_other_workspaces(): void
    {
        [, $otherWorkspace] = $this->createOtherWorkspace();

        $otherClient = Client::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->getJson("/api/v1/clients/{$otherClient->id}")
            ->assertNotFound();
    }
}
