<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class GraphQLApiTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_login_mutation_creates_a_sanctum_token(): void
    {
        $this->user->forceFill([
            'password' => Hash::make('password'),
        ])->save();

        $this->graphQL('
            mutation Login($email: String!, $password: String!, $device: String!) {
                login(email: $email, password: $password, device_name: $device) {
                    token
                    type
                    user {
                        id
                        email
                    }
                }
            }
        ', [
            'email' => $this->user->email,
            'password' => 'password',
            'device' => 'feature-test',
        ])
            ->assertOk()
            ->assertJsonPath('data.login.type', 'Bearer')
            ->assertJsonPath('data.login.user.email', $this->user->email)
            ->assertJsonMissingPath('errors');
    }

    public function test_clients_query_is_workspace_scoped_and_paginated(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        Client::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        [, $otherWorkspace] = $this->createOtherWorkspace();

        Client::factory()->count(2)->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->graphQL('
            query {
                clients(first: 10) {
                    data {
                        id
                        name
                        status_label
                        projects_count
                    }
                    paginatorInfo {
                        total
                        perPage
                        currentPage
                    }
                }
            }
        ')
            ->assertOk()
            ->assertJsonPath('data.clients.paginatorInfo.total', 3)
            ->assertJsonCount(3, 'data.clients.data')
            ->assertJsonMissingPath('errors');
    }

    public function test_create_invoice_mutation_uses_existing_invoice_service(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $project = Project::factory()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->graphQL('
            mutation CreateInvoice($input: CreateInvoiceInput!) {
                createInvoice(input: $input) {
                    id
                    number
                    status
                    subtotal
                    tax_amount
                    total
                    client {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'input' => [
                'client_id' => (string) $client->id,
                'project_id' => (string) $project->id,
                'tax_rate' => 18,
                'issued_at' => '2026-06-14',
                'due_at' => '2026-06-30',
                'line_items' => [
                    ['description' => 'Discovery', 'quantity' => 2, 'rate' => 1000],
                    ['description' => 'Build', 'quantity' => 1, 'rate' => 3000],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.createInvoice.status', 'draft')
            ->assertJsonPath('data.createInvoice.subtotal', 5000)
            ->assertJsonPath('data.createInvoice.tax_amount', 900)
            ->assertJsonPath('data.createInvoice.total', 5900)
            ->assertJsonPath('data.createInvoice.client.id', (string) $client->id)
            ->assertJsonPath('data.createInvoice.project.id', (string) $project->id)
            ->assertJsonMissingPath('errors');

        $this->assertDatabaseHas('invoices', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'subtotal' => 5000,
            'tax_amount' => 900,
            'total' => 5900,
        ]);
    }

    public function test_dashboard_stats_query_reuses_workspace_scoped_metrics(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $client = Client::factory()->active()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        Project::factory()->active()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        Invoice::factory()->paid()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'total' => 1200,
            'paid_at' => now(),
        ]);

        $this->graphQL('
            query {
                dashboardStats {
                    total_clients
                    active_projects
                    total_revenue
                    revenue_this_month
                }
            }
        ')
            ->assertOk()
            ->assertJsonPath('data.dashboardStats.total_clients', 1)
            ->assertJsonPath('data.dashboardStats.active_projects', 1)
            ->assertJsonPath('data.dashboardStats.total_revenue', 1200)
            ->assertJsonPath('data.dashboardStats.revenue_this_month', 1200)
            ->assertJsonMissingPath('errors');
    }

    private function graphQL(string $query, array $variables = []): TestResponse
    {
        return $this->postJson('/graphql', [
            'query' => $query,
            'variables' => $variables,
        ]);
    }
}
