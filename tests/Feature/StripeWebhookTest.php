<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Helpers\StripeWebhookHelper;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashier.secret' => 'sk_test_fake',
            'cashier.webhook.secret' => 'whsec_test_secret',
        ]);

        $this->setUpWorkspace();
    }

    public function test_payment_succeeded_marks_invoice_as_paid(): void
    {
        $invoice = $this->invoiceWithPaymentIntent('pi_test_123');

        ['payload' => $payload, 'signature' => $signature] = StripeWebhookHelper::paymentSucceeded(
            'pi_test_123',
            5900000,
            ['invoice_id' => $invoice->id],
        );

        $this->postSignedWebhook($payload, $signature)->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'stripe_payment_status' => 'succeeded',
        ]);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_payment_failed_updates_stripe_payment_status(): void
    {
        $invoice = $this->invoiceWithPaymentIntent('pi_test_456');

        ['payload' => $payload, 'signature' => $signature] = StripeWebhookHelper::paymentFailed('pi_test_456');

        $this->postSignedWebhook($payload, $signature)->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'stripe_payment_status' => 'payment_failed',
        ]);
    }

    public function test_payment_processing_updates_stripe_payment_status(): void
    {
        $invoice = $this->invoiceWithPaymentIntent('pi_test_789');

        ['payload' => $payload, 'signature' => $signature] = StripeWebhookHelper::paymentProcessing('pi_test_789');

        $this->postSignedWebhook($payload, $signature)->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'stripe_payment_status' => 'processing',
        ]);
    }

    public function test_webhook_with_invalid_signature_returns_400(): void
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded'], JSON_THROW_ON_ERROR);

        $this->postSignedWebhook($payload, 'invalid_signature')->assertBadRequest();
    }

    public function test_webhook_with_unknown_payment_intent_returns_200_gracefully(): void
    {
        ['payload' => $payload, 'signature' => $signature] = StripeWebhookHelper::paymentSucceeded(
            'pi_nonexistent',
            10000,
        );

        $this->postSignedWebhook($payload, $signature)->assertOk();
    }

    private function postSignedWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
    }

    private function invoiceWithPaymentIntent(string $paymentIntentId): Invoice
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        return Invoice::factory()->sent()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_payment_status' => 'requires_payment_method',
        ]);
    }
}
