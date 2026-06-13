<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_invoice_number_is_auto_generated(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $invoice = app(InvoiceService::class)->create([
            'client_id' => $client->id,
            'tax_rate' => 18.0,
            'line_items' => [
                ['description' => 'Web Design', 'quantity' => 1, 'rate' => 50000],
            ],
            'status' => 'draft',
            'workspace_id' => $this->workspace->id,
        ]);

        $this->assertStringStartsWith('INV-', $invoice->number);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{3}$/', $invoice->number);
    }

    public function test_invoice_totals_are_calculated_correctly(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $invoice = app(InvoiceService::class)->create([
            'client_id' => $client->id,
            'tax_rate' => 18.0,
            'line_items' => [
                ['description' => 'Design', 'quantity' => 2, 'rate' => 10000],
                ['description' => 'Dev', 'quantity' => 1, 'rate' => 30000],
            ],
            'status' => 'draft',
            'workspace_id' => $this->workspace->id,
        ]);

        $this->assertEquals(50000, $invoice->subtotal);
        $this->assertEquals(9000, $invoice->tax_amount);
        $this->assertEquals(59000, $invoice->total);
    }

    public function test_mark_as_sent_updates_status_and_sets_issued_at(): void
    {
        $invoice = $this->invoiceWithClient(['status' => 'draft']);

        $invoice->markAsSent();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'sent',
        ]);

        $this->assertNotNull($invoice->fresh()->issued_at);
    }

    public function test_mark_as_paid_updates_status_and_sets_paid_at(): void
    {
        $invoice = $this->invoiceWithClient(['status' => 'sent']);

        $invoice->markAsPaid();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);

        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_invoice_is_overdue_when_past_due_date_and_not_paid(): void
    {
        $invoice = $this->invoiceWithClient([
            'status' => 'sent',
            'due_at' => now()->subDays(5),
        ]);

        $this->assertTrue($invoice->is_overdue);
    }

    public function test_paid_invoice_is_not_overdue_even_if_past_due_date(): void
    {
        $invoice = $this->invoiceWithClient([
            'status' => 'paid',
            'due_at' => now()->subDays(5),
            'paid_at' => now()->subDays(2),
        ]);

        $this->assertFalse($invoice->is_overdue);
    }

    private function invoiceWithClient(array $attributes = []): Invoice
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        return Invoice::factory()->create(array_merge([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ], $attributes));
    }
}
