<?php

namespace Tests\Browser;

use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ClientSearchTest extends DuskTestCase
{
    use DatabaseTruncation;
    use DuskWithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_live_search_filters_clients_as_user_types(): void
    {
        Client::factory()->create([
            'name' => 'Acme Corporation',
            'company' => null,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        Client::factory()->create([
            'name' => 'Beta Industries',
            'company' => null,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        Client::factory()->create([
            'name' => 'Acme Consulting',
            'company' => null,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/clients')
                ->waitFor('@client-search')
                ->type('@client-search', 'Acme')
                ->waitForText('Acme Corporation')
                ->waitUntilMissingText('Beta Industries')
                ->assertSee('Acme Consulting')
                ->assertDontSee('Beta Industries');
        });
    }

    public function test_clearing_search_shows_all_clients(): void
    {
        Client::factory()->create([
            'name' => 'Acme Corp',
            'company' => null,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        Client::factory()->create([
            'name' => 'Beta Ltd',
            'company' => null,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/clients')
                ->waitFor('@client-search')
                ->type('@client-search', 'Acme')
                ->waitUntilMissingText('Beta Ltd')
                ->click('@clear-client-search')
                ->waitForText('Beta Ltd')
                ->assertSee('Acme Corp');
        });
    }

    public function test_status_filter_buttons_filter_the_client_list(): void
    {
        Client::factory()->active()->create([
            'name' => 'Active Client',
            'company' => null,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        Client::factory()->lead()->create([
            'name' => 'Lead Client',
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/clients')
                ->waitFor('@client-status-lead')
                ->click('@client-status-lead')
                ->waitUntilMissingText('Active Client')
                ->assertSee('Lead Client')
                ->click('@client-status-all')
                ->waitForText('Active Client')
                ->assertSee('Lead Client');
        });
    }
}
