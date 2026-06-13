<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CreateClientTest extends DuskTestCase
{
    use DatabaseTruncation;
    use DuskWithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_user_can_create_a_client_via_the_form(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/clients/create')
                ->waitFor('@client-name')
                ->type('@client-name', 'New Test Client')
                ->type('@client-email', 'newtestclient@example.com')
                ->type('@client-phone', '+91 98765 43210')
                ->type('@client-company', 'Test Corp')
                ->select('@client-status', 'active')
                ->click('@save-client')
                ->waitForLocation('/clients')
                ->waitForText('Client added successfully.')
                ->assertSee('New Test Client');
        });
    }

    public function test_form_shows_validation_errors_on_empty_submit(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/clients/create')
                ->waitFor('@save-client')
                ->click('@save-client')
                ->waitForText('The name is required')
                ->assertSee('The email is required');
        });
    }

    public function test_real_time_email_validation_fires_on_input(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/clients/create')
                ->waitFor('@client-email')
                ->type('@client-email', 'not-an-email')
                ->waitForText('The email must be a valid email address.')
                ->clear('@client-email')
                ->type('@client-email', 'valid@example.com')
                ->waitUntilMissingText('The email must be a valid email address.')
                ->assertInputValue('@client-email', 'valid@example.com');
        });
    }
}
