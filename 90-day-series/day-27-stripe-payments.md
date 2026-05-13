# Day 27 — Stripe Payments — FreelanceFlow Gets Paid

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 17 min · **Level:** Intermediate

---

> *"An invoice is just a piece of paper until it is paid. Today FreelanceFlow accepts real money. We integrate Stripe, create a payment intent for an invoice, build a payment page using Stripe Elements, handle the payment webhook, and automatically mark invoices as paid when Stripe confirms the charge. By the end of today FreelanceFlow can receive payments from anywhere in the world."*

---

## What We Are Building Today

1. **Install Laravel Cashier** — Stripe's official Laravel package
2. **Stripe account setup** — test keys and webhook configuration
3. **Payment intent creation** — server-side Stripe API call
4. A **payment page** — Stripe Elements for the card form
5. **Webhook handling** — listen for Stripe events, mark invoices paid
6. **Webhook signature verification** — security for incoming Stripe events
7. **Test the full payment flow** end to end

---

## Step 1 — Install Laravel Cashier

Cashier is Laravel's official Stripe integration. It handles payment intents, subscriptions, invoices, and webhooks.

```bash
composer require laravel/cashier
```

Publish and run the Cashier migrations:

```bash
php artisan vendor:publish --tag="cashier-migrations"
php artisan migrate
```

This adds Stripe-specific columns to the `users` table (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`) and creates a `subscriptions` table and `subscription_items` table.

For FreelanceFlow we use Cashier for one-time payments — not subscriptions. The subscription tables stay empty for now.

Add the `Billable` trait to the User model:

```php
// app/Models/User.php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Billable;
}
```

---

## Step 2 — Stripe Configuration

Add your Stripe keys to `.env`:

```env
STRIPE_KEY=pk_test_your_publishable_key_here
STRIPE_SECRET=sk_test_your_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here
```

Get these from the Stripe Dashboard at `dashboard.stripe.com` → Developers → API keys. Use the **test** keys during development — all transactions use test card numbers and no real money moves.

Update `config/cashier.php`:

```php
// config/cashier.php
return [
    'key'      => env('STRIPE_KEY'),
    'secret'   => env('STRIPE_SECRET'),
    'webhook'  => [
        'secret'    => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('CASHIER_WEBHOOK_TOLERANCE', 300),
    ],
    'currency' => env('CASHIER_CURRENCY', 'inr'),  // INR for FreelanceFlow
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en_IN'),
];
```

---

## Step 3 — Add Payment Columns to Invoices

The invoices table needs to track Stripe payment intent data:

```bash
php artisan make:migration add_stripe_fields_to_invoices_table
```

```php
public function up(): void
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->string('stripe_payment_intent_id')->nullable()->after('pdf_path');
        $table->string('stripe_payment_status')->nullable()->after('stripe_payment_intent_id');
        // requires_payment_method | requires_confirmation | processing | succeeded | canceled
    });
}
```

```bash
php artisan migrate
```

Update `$fillable` on the Invoice model:

```php
protected $fillable = [
    // ... existing fields
    'stripe_payment_intent_id',
    'stripe_payment_status',
];
```

---

## Step 4 — The Payment Controller

Create a controller to handle payment intent creation and the payment page:

```bash
php artisan make:controller PaymentController
```

```php
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
```

Add routes:

```php
// routes/web.php
use App\Http\Controllers\PaymentController;

// Payment routes — these can be public (clients do not need to log in to pay)
Route::get('/invoices/{invoice}/pay',         [PaymentController::class, 'show'])->name('invoices.pay');
Route::get('/invoices/{invoice}/pay/success', [PaymentController::class, 'success'])->name('invoices.pay.success');
```

---

## Step 5 — The Payment Page

Create `resources/views/payments/show.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice {{ $invoice->number }} — FreelanceFlow</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    {{-- Stripe.js — always load from Stripe's CDN, never self-host --}}
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="min-h-screen bg-gray-50 font-sans antialiased flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        {{-- Invoice summary card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-lg font-bold text-gray-900">FreelanceFlow</p>
                    <p class="text-sm text-gray-500">Invoice {{ $invoice->number }}</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-gray-900">{{ $invoice->formatted_total }}</p>
                    @if ($invoice->due_at)
                        <p class="text-xs text-gray-400 mt-0.5">
                            Due {{ $invoice->due_at->format('M d, Y') }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="border-t border-gray-100 pt-3">
                <p class="text-sm font-medium text-gray-700">{{ $invoice->client->name }}</p>
                @if ($invoice->client->company)
                    <p class="text-xs text-gray-400">{{ $invoice->client->company }}</p>
                @endif
            </div>
        </div>

        {{-- Payment form card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Payment details</h2>

            <form id="payment-form">
                {{-- Stripe Elements mounts here --}}
                <div id="payment-element" class="mb-4"></div>

                {{-- Error messages --}}
                <div id="payment-errors" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

                <button
                    id="submit-btn"
                    type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed
                           text-white font-medium py-2.5 px-4 rounded-lg transition-colors"
                >
                    <span id="btn-text">Pay {{ $invoice->formatted_total }}</span>
                    <span id="btn-spinner" class="hidden">Processing...</span>
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-gray-400">
                Secured by
                <span class="font-medium text-gray-500">Stripe</span>
                · Your card details are never stored on our servers
            </p>
        </div>

    </div>

    <script>
        const stripe = Stripe('{{ $stripePublicKey }}');

        const elements = stripe.elements({
            clientSecret: '{{ $clientSecret }}',
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#6366f1',
                    fontFamily: 'Inter, sans-serif',
                    borderRadius: '8px',
                },
            },
        });

        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        const form        = document.getElementById('payment-form');
        const submitBtn   = document.getElementById('submit-btn');
        const btnText     = document.getElementById('btn-text');
        const btnSpinner  = document.getElementById('btn-spinner');
        const errorDiv    = document.getElementById('payment-errors');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Disable the button to prevent double submission
            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');
            errorDiv.classList.add('hidden');

            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: '{{ route('invoices.pay.success', $invoice) }}',
                },
            });

            // Only runs if there is an immediate error
            // (successful payments redirect automatically)
            if (error) {
                errorDiv.textContent = error.message;
                errorDiv.classList.remove('hidden');

                submitBtn.disabled = false;
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
            }
        });
    </script>

</body>
</html>
```

Create `resources/views/payments/success.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Received — FreelanceFlow</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 font-sans antialiased flex items-center justify-center">
    <div class="text-center max-w-sm">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment received</h1>
        <p class="text-gray-500 mb-1">Invoice {{ $invoice->number }}</p>
        <p class="text-3xl font-bold text-gray-900 mb-6">{{ $invoice->formatted_total }}</p>
        <p class="text-sm text-gray-400">
            Thank you for your payment. A receipt has been sent to your email.
        </p>
    </div>
</body>
</html>
```

---

## Step 6 — Webhook Handling

The payment page redirects to the success URL immediately after the user confirms payment. But the actual charge confirmation from Stripe arrives as a webhook — an HTTP POST to your server.

**Never mark an invoice as paid based on the redirect URL alone.** The redirect can be faked. The webhook is cryptographically signed by Stripe and cannot be spoofed.

Register the webhook route — outside the CSRF middleware:

```php
// routes/web.php
// Webhooks must be excluded from CSRF verification
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
     ->name('stripe.webhook');
```

Exclude the webhook route from CSRF in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->preventRequestForgery(except: [
        'stripe/webhook',
    ]);
})
```

Create the webhook controller:

```bash
php artisan make:controller StripeWebhookController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        Stripe::setApiKey(config('cashier.secret'));

        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret    = config('cashier.webhook.secret');

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
            'payment_intent.succeeded'               => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed'          => $this->handlePaymentFailed($event->data->object),
            'payment_intent.processing'              => $this->handlePaymentProcessing($event->data->object),
            default                                  => Log::info("Unhandled Stripe event: {$event->type}"),
        };

        // Always return 200 to acknowledge receipt
        // Stripe retries webhooks that do not receive a 200
        return response('Webhook received.', 200);
    }

    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        $invoice = Invoice::where(
            'stripe_payment_intent_id',
            $paymentIntent->id
        )->first();

        if (! $invoice) {
            Log::warning('Payment succeeded but no matching invoice found', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        $invoice->update([
            'stripe_payment_status' => 'succeeded',
        ]);

        $invoice->markAsPaid();

        Log::info('Invoice marked as paid via Stripe webhook', [
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

            Log::warning('Stripe payment failed', [
                'invoice_id'        => $invoice->id,
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
```

---

## Step 7 — Test Webhooks Locally with Stripe CLI

Stripe webhooks need a public URL. In local development, use the Stripe CLI to forward events to your local server:

```bash
# Install Stripe CLI (macOS)
brew install stripe/stripe-cli/stripe

# Login to Stripe
stripe login

# Forward webhooks to your local server
stripe listen --forward-to http://localhost:8000/stripe/webhook

# Output:
# Ready! Your webhook signing secret is whsec_... (copy this to .env)
```

Copy the `whsec_...` secret to your `.env` as `STRIPE_WEBHOOK_SECRET`.

Now trigger a test payment event:

```bash
# Simulate a successful payment
stripe trigger payment_intent.succeeded

# Or use the test card on the payment page
# Card number: 4242 4242 4242 4242
# Expiry: any future date
# CVC: any 3 digits
# ZIP: any 5 digits
```

Watch your queue worker terminal — the webhook fires, the handler processes it, the invoice is marked paid.

---

## Step 8 — Add a Pay Button to the Invoice List

Update the invoice card or detail view to show a payment link:

```blade
@if (in_array($invoice->status, ['sent', 'overdue']))
    <a
        href="{{ route('invoices.pay', $invoice) }}"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700
               text-white text-sm font-medium px-3 py-1.5 rounded-md transition-colors"
        target="_blank"
    >
        Pay {{ $invoice->formatted_total }}
    </a>
@endif

@if ($invoice->status === 'paid')
    <span class="inline-flex items-center gap-1 text-green-700 text-sm font-medium">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
        </svg>
        Paid {{ $invoice->paid_at?->format('M d, Y') }}
    </span>
@endif
```

---

## Stripe Test Cards

| Card number | Result |
|---|---|
| `4242 4242 4242 4242` | Payment succeeds |
| `4000 0000 0000 0002` | Card declined |
| `4000 0025 0000 3155` | Requires 3D Secure authentication |
| `4000 0000 0000 9995` | Insufficient funds |
| `4000 0000 0000 0069` | Expired card |

All use: any future expiry date, any 3-digit CVC, any 5-digit postal code.

---

## Payment Flow Summary

```
1. User clicks "Pay Invoice"
2. PaymentController::show() creates a PaymentIntent via Stripe API
3. Payment page loads with Stripe Elements (hosted card form)
4. User enters card details and clicks Pay
5. Stripe.js confirms the payment with the client_secret
6. Stripe redirects to /invoices/{id}/pay/success
7. Simultaneously: Stripe sends payment_intent.succeeded webhook to /stripe/webhook
8. Webhook handler verifies signature → finds invoice → marks as paid
9. Invoice status updates to 'paid', paid_at recorded
```

Steps 7–9 happen in the background independently of the redirect. Even if the redirect fails (user closes the tab), the webhook still fires and the invoice is marked paid.

---

## What We Learned Today

- **Stripe PaymentIntent** — the server-side object that represents a payment. Created once per invoice, reused if the user returns to the payment page
- **`amount` in smallest currency unit** — Stripe uses paise for INR, cents for USD. Multiply by 100: ₹500 → 50000 paise
- **Stripe Elements** — Stripe's hosted card form. PCI-compliant. Card details never touch your server
- **`stripe.confirmPayment()`** — the JS call that completes the payment and triggers the redirect
- **Webhook signature verification** — `Webhook::constructEvent($payload, $signature, $secret)`. Never trust a webhook that fails signature verification
- **Always return 200 from webhook handlers** — Stripe retries webhooks that do not receive 200. Return 200 even if you decide to ignore an event
- **Never mark invoices paid on redirect alone** — the redirect can be visited directly without payment. The webhook is the source of truth
- **CSRF exclusion for webhooks** — webhook routes must be excluded from CSRF verification. Stripe cannot send a CSRF token
- **Stripe CLI for local testing** — `stripe listen --forward-to localhost:8000/stripe/webhook` forwards real Stripe events to your local server

---

## Day 28 — Dashboard with Charts

Tomorrow FreelanceFlow gets a real dashboard. Total revenue, active projects, unpaid invoices, overdue clients — all visualised with charts using Chart.js integrated into Livewire components. The dashboard becomes the first page users see after login and gives them a complete picture of their freelance business at a glance.

See you on Day 28.
