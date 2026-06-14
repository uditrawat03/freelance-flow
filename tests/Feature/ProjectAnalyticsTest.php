<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class ProjectAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_project_analytics_page_returns_bounded_inertia_payload_with_aggregate_stats(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'budget' => 100000,
            'status' => 'active',
        ]));

        Invoice::factory()->count(55)->sent()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'total' => 1000,
        ]);

        Invoice::factory()->paid()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'total' => 500,
        ]);

        $this->get(route('projects.analytics', $project))
            ->assertOk()
            ->assertSee('Search clients, projects, invoices')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Analytics')
                ->where('project.id', $project->id)
                ->where('project.client.name', $client->name)
                ->where('stats.invoice_count', 56)
                ->where('stats.total_invoiced', 'INR 55,500.00')
                ->where('stats.total_paid', 'INR 500.00')
                ->where('stats.total_outstanding', 'INR 55,000.00')
                ->where('stats.has_more_invoices', true)
                ->has('invoices', 50)
            );
    }
}
