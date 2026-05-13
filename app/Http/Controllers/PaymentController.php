<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Stripe\Stripe;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Show the payment page for an invoice.
     * GET /invoices/{invoice}/pay
     */
    public function show(Invoice $invoice): View|RedirectResponse
    {
        // Cannot pay a draft or already-paid invoice
        if (! in_array($invoice->status, ['sent', 'overdue'])) {
            return redirect()->back()->with('error', 'This invoice cannot be paid.');
        }

        // Create or retrieve the payment intent
        if (! $invoice->stripe_payment_intent_id) {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount'               => (int) ($invoice->total * 100), // Stripe uses paise/cents
                'currency'             => config('cashier.currency'),
                'metadata'             => [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'client_id'      => $invoice->client_id,
                ],
                'description'          => "Payment for invoice {$invoice->number}",
                'automatic_payment_methods' => ['enabled' => true],
            ]);

            $invoice->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_payment_status'    => $paymentIntent->status,
            ]);
        } else {
            // Retrieve the existing payment intent
            $paymentIntent = \Stripe\PaymentIntent::retrieve(
                $invoice->stripe_payment_intent_id
            );
        }

        $invoice->loadMissing('client');

        return view('payments.show', [
            'invoice'          => $invoice,
            'clientSecret'     => $paymentIntent->client_secret,
            'stripePublicKey'  => config('cashier.key'),
        ]);
    }

    /**
     * Payment success page — after Stripe redirect.
     * GET /invoices/{invoice}/pay/success
     */
    public function success(Invoice $invoice): View
    {
        return view('payments.success', compact('invoice'));
    }
}