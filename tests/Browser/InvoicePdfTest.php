<?php

namespace Tests\Browser;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InvoicePdfTest extends DuskTestCase
{
    use DatabaseTruncation;
    use DuskWithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_user_can_generate_an_invoice_pdf(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        $invoice = Invoice::factory()->draft()->create([
            'number' => 'INV-DUSK-001',
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) use ($invoice) {
            $this->loginWith($browser);

            $browser->visit('/invoices')
                ->waitForText('INV-DUSK-001')
                ->click("@generate-invoice-pdf-{$invoice->id}")
                ->waitForText('Generate PDF?')
                ->click('@confirm-generate-invoice-pdf')
                ->waitFor("@download-invoice-pdf-{$invoice->id}")
                ->assertVisible("@download-invoice-pdf-{$invoice->id}");
        });
    }
}
