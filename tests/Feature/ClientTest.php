<?php

namespace Tests\Feature;

use App\Livewire\Clients\Create;
use App\Livewire\Clients\Edit;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class ClientTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_authenticated_user_can_view_client_list(): void
    {
        Client::factory()->count(5)->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->get(route('clients.index'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();

        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_a_client_with_livewire(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Acme Corp')
            ->set('email', 'hello@acme.com')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'name' => 'Acme Corp',
            'email' => 'hello@acme.com',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_client_creation_requires_name_and_email(): void
    {
        Livewire::test(Create::class)
            ->set('name', '')
            ->set('email', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'email' => 'required']);
    }

    public function test_user_can_view_their_own_client(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->get(route('clients.show', $client))->assertOk();
    }

    public function test_user_cannot_view_client_from_another_workspace(): void
    {
        [, $otherWorkspace] = $this->createOtherWorkspace();

        $otherClient = Client::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->get(route('clients.show', $otherClient))->assertNotFound();
    }

    public function test_user_can_update_their_client_with_livewire(): void
    {
        $client = Client::factory()->create([
            'name' => 'Old Name',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        Livewire::test(Edit::class, ['client' => $client])
            ->set('name', 'New Name')
            ->set('email', $client->email)
            ->set('status', 'lead')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'New Name',
            'status' => 'lead',
        ]);
    }

    public function test_user_can_soft_delete_their_client_with_livewire(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        Livewire::test(Edit::class, ['client' => $client])
            ->call('delete')
            ->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_workspace_scope_isolates_data(): void
    {
        Client::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        [, $otherWorkspace] = $this->createOtherWorkspace();

        Client::factory()->count(5)->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->assertSame(3, Client::count());
    }
}
