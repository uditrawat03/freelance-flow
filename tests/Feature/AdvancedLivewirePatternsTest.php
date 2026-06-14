<?php

namespace Tests\Feature;

use App\Livewire\Clients\Stats;
use App\Livewire\Invoices\QuickCreate;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class AdvancedLivewirePatternsTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_client_stats_show_workspace_counts(): void
    {
        Livewire::withoutLazyLoading();

        Client::factory()->create([
            'status' => 'active',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        Client::factory()->create([
            'status' => 'inactive',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        Client::factory()->create([
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        [, $otherWorkspace] = $this->createOtherWorkspace();
        Client::factory()->count(2)->create([
            'status' => 'active',
            'workspace_id' => $otherWorkspace->id,
        ]);

        Livewire::test(Stats::class)
            ->assertSee('Total clients')
            ->assertSee('3')
            ->assertSee('Active')
            ->assertSee('Inactive')
            ->assertSee('Leads');
    }

    public function test_quick_create_creates_draft_invoice_and_dispatches_refresh_event(): void
    {
        $client = Client::factory()->create([
            'status' => 'active',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        Livewire::test(QuickCreate::class)
            ->call('openModal')
            ->set('client_id', $client->id)
            ->set('description', 'Landing page sprint')
            ->set('amount', 50000)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertDispatched('invoice-created')
            ->assertDispatched('notify');

        $invoice = Invoice::firstOrFail();

        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame('draft', $invoice->status);
        $this->assertEquals(50000, $invoice->subtotal);
        $this->assertEquals(59000, $invoice->total);
        $this->assertSame($this->workspace->id, $invoice->workspace_id);
    }

    public function test_quick_create_rejects_clients_from_other_workspaces(): void
    {
        [, $otherWorkspace] = $this->createOtherWorkspace();

        $otherClient = Client::factory()->create([
            'status' => 'active',
            'workspace_id' => $otherWorkspace->id,
        ]);

        Livewire::test(QuickCreate::class)
            ->set('client_id', $otherClient->id)
            ->set('description', 'Cross workspace invoice')
            ->set('amount', 50000)
            ->call('save')
            ->assertHasErrors(['client_id']);

        $this->assertDatabaseCount('invoices', 0);
    }
}
