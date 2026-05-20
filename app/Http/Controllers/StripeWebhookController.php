<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Services\Logger;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    public function handle(Request $request): Response
    {
        Stripe::setApiKey(config('cashier.secret'));

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        // Verify the webhook signature — this is critical
        // An invalid signature means the request did not come from Stripe
        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid signature.', 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook parsing failed', ['error' => $e->getMessage()]);
            return response('Webhook error.', 400);
        }

        // Handle the event
        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'payment_intent.processing' => $this->handlePaymentProcessing($event->data->object),
            default => Log::info("Unhandled Stripe event: {$event->type}"),
        };

        // Always return 200 to acknowledge receipt
        // Stripe retries webhooks that do not receive a 200
        return response('Webhook received.', 200);
    }

    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $invoice) {
            $this->logger->payment('Payment succeeded but no matching invoice found', [
                'payment_intent_id' => $paymentIntent->id,
                'amount'            => $paymentIntent->amount / 100,
            ]);
            return;
        }

        $invoice->markAsPaid();

        $this->logger->payment('Invoice marked as paid via Stripe webhook', [
            'invoice_id'        => $invoice->id,
            'invoice_number'    => $invoice->number,
            'payment_intent_id' => $paymentIntent->id,
            'amount'            => $paymentIntent->amount / 100,
        ]);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $invoice = Invoice::where(
            'stripe_payment_intent_id',
            $paymentIntent->id
        )->first();

        if ($invoice) {
            $invoice->update(['stripe_payment_status' => 'payment_failed']);

            $this->logger->payment('Stripe payment failed', [
                'invoice_id' => $invoice->id,
                'payment_intent_id' => $paymentIntent->id,
            ]);
        }
    }

    private function handlePaymentProcessing(object $paymentIntent): void
    {
        Invoice::where('stripe_payment_intent_id', $paymentIntent->id)
            ->update(['stripe_payment_status' => 'processing']);
    }
}