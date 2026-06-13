# Day 42 - HTTP Tests & Mocking

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 3 - Advanced**  
> **Read time:** 15 min | **Level:** Intermediate

---

Day 41 covered model and service tests. Today we test the production edges that are easiest to break: signed Stripe webhooks and outbound HTTP calls. The key rule is simple: tests should verify our code without calling real external services.

---

## What We Built Today

1. A `StripeWebhookHelper` that creates real Stripe-style signed payloads.
2. Webhook tests for `payment_intent.succeeded`, `payment_intent.payment_failed`, and `payment_intent.processing`.
3. Invalid-signature and unknown-payment-intent webhook coverage.
4. `Http::fake()` tests for Slack notifications and common external API patterns.
5. Mock coverage for URL-specific fakes, fallback fakes, response sequences, server failures, connection failures, stray request protection, and `assertNothingSent()`.

---

## Step 1 - Stripe Webhook Helper

Stripe signs the exact raw request body. If the test signs one JSON string but sends a re-encoded version of that JSON, signature verification fails. Keep the payload as a raw string and post that exact string in the test.

Create `tests/Helpers/StripeWebhookHelper.php`:

```php
<?php

namespace Tests\Helpers;

class StripeWebhookHelper
{
    public static function makePayload(
        string $eventType,
        array $data,
        ?string $secret = null,
        ?int $timestamp = null,
    ): array {
        $secret ??= config('cashier.webhook.secret', 'whsec_test_secret');
        $timestamp ??= time();

        $payload = json_encode([
            'id' => 'evt_test_' . uniqid(),
            'object' => 'event',
            'type' => $eventType,
            'created' => $timestamp,
            'livemode' => false,
            'data' => [
                'object' => $data,
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return [
            'payload' => $payload,
            'signature' => "t={$timestamp},v1={$signature}",
        ];
    }

    public static function paymentSucceeded(string $paymentIntentId, int $amount, array $metadata = []): array
    {
        return self::makePayload('payment_intent.succeeded', [
            'id' => $paymentIntentId,
            'object' => 'payment_intent',
            'amount' => $amount,
            'currency' => 'inr',
            'status' => 'succeeded',
            'metadata' => $metadata,
        ]);
    }

    public static function paymentFailed(string $paymentIntentId): array
    {
        return self::makePayload('payment_intent.payment_failed', [
            'id' => $paymentIntentId,
            'object' => 'payment_intent',
            'status' => 'requires_payment_method',
        ]);
    }

    public static function paymentProcessing(string $paymentIntentId): array
    {
        return self::makePayload('payment_intent.processing', [
            'id' => $paymentIntentId,
            'object' => 'payment_intent',
            'status' => 'processing',
        ]);
    }
}
```

Important fix: use the full webhook secret when building the HMAC. Do not strip `whsec_` from the secret.

---

## Step 2 - Stripe Webhook Tests

Create `tests/Feature/StripeWebhookTest.php`.

The tests configure fake Stripe keys, create an invoice with a Stripe payment intent id, sign a payload, and send the raw payload to `/stripe/webhook`.

Covered cases:

- `payment_intent.succeeded` marks the invoice paid and stores `stripe_payment_status = succeeded`.
- `payment_intent.payment_failed` stores `stripe_payment_status = payment_failed`.
- `payment_intent.processing` stores `stripe_payment_status = processing`.
- Invalid signatures return `400`.
- Unknown payment intents still return `200` so Stripe does not keep retrying.

The helper method that posts the webhook should use `call()` with raw content:

```php
private function postSignedWebhook(string $payload, string $signature): TestResponse
{
    return $this->call('POST', '/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $payload);
}
```

Avoid this pattern for signed webhooks:

```php
$this->postJson('/stripe/webhook', json_decode($payload, true));
```

`postJson()` may re-encode the body, which means Stripe receives bytes that differ from the bytes used to generate the signature.

---

## Step 3 - Persist Stripe Success Status

The webhook controller already called `markAsPaid()`, but the Stripe-specific status was not updated on success. Update the success handler:

```php
$invoice->markAsPaid();
$invoice->update(['stripe_payment_status' => 'succeeded']);
```

Now the invoice has both business state (`status = paid`) and gateway state (`stripe_payment_status = succeeded`).

---

## Step 4 - HTTP Mock Tests

Create `tests/Feature/SlackNotificationTest.php`.

The real external seam in this app is `App\Listeners\NotifyTeamOnSlack`, which posts to `config('services.slack.webhook')`. The listener is tested directly so we can assert the outgoing request without requiring the listener to be enabled in the event provider.

```php
Http::fake([
    'hooks.slack.com/*' => Http::response(['ok' => true], 200),
]);

$project = $this->projectWithoutEvents('Client Portal');

app(NotifyTeamOnSlack::class)->handle(new ProjectCreated($project));

Http::assertSent(function ($request) use ($project) {
    return $request->url() === config('services.slack.webhook')
        && $request->method() === 'POST'
        && str_contains($request['text'], $project->name)
        && str_contains($request['text'], $project->client->name);
});

Http::assertSentCount(1);
```

Use `Project::withoutEvents()` in this test helper. That keeps project creation from dispatching unrelated listeners while the test focuses only on the Slack listener.

---

## Step 5 - Http::fake() Patterns Covered

### Specific URL and Fallback

```php
Http::fake([
    'api.example.com/clients' => Http::response([
        'clients' => [
            ['id' => 1, 'name' => 'Test Client'],
        ],
    ], 200),
    'api.example.com/*' => Http::response(['message' => 'Not found'], 404),
]);
```

Put the most specific fake first and the wildcard fallback after it.

### Response Sequences

```php
Http::fake([
    'api.example.com/jobs/*' => Http::sequence()
        ->push(['status' => 'processing'], 202)
        ->push(['status' => 'complete'], 200)
        ->push(['status' => 'failed'], 500),
]);
```

Sequences are useful for polling, retries, and state transitions.

### Server Failures

```php
Http::fake([
    'api.example.com/*' => Http::response([], 500),
]);
```

Use this to test code paths that handle failed responses.

### Connection Failures

```php
Http::fake([
    'api.example.com/*' => Http::failedConnection(),
]);
```

Use this when the remote service cannot be reached at all.

### Stray Request Protection

```php
Http::preventStrayRequests();

Http::fake([
    'api.example.com/allowed' => Http::response(['ok' => true], 200),
]);
```

This catches any HTTP call that your test forgot to fake.

### No Requests Sent

```php
Http::fake();

Http::assertNothingSent();
```

Use this when a guard clause or disabled integration should skip outbound HTTP entirely.

---

## Step 6 - Run The Focused Tests

```bash
php artisan test tests/Feature/StripeWebhookTest.php tests/Feature/SlackNotificationTest.php
```

Expected result:

```text
PASS  Tests\Feature\StripeWebhookTest
PASS  Tests\Feature\SlackNotificationTest

Tests: 12 passed
```

Then run the full suite:

```bash
php artisan test
```

---

## What We Learned Today

- `Http::fake()` intercepts outbound HTTP and keeps tests off the real network.
- `Http::assertSent()` verifies the URL, method, and request body.
- `Http::sequence()` models APIs that return different responses across repeated calls.
- `Http::failedConnection()` tests network exceptions, not just HTTP error responses.
- `Http::preventStrayRequests()` is a safety net against unmocked outbound calls.
- Stripe webhook signatures must be generated from the exact raw payload sent to the route.
- Webhook handlers should acknowledge unknown but valid events with `200` unless there is a true request problem.

---

## Day 43 - Browser Testing With Dusk

Tomorrow we move from HTTP tests to browser tests. Dusk lets us test flows that require JavaScript: Livewire interactions, file uploads in the browser, and invoice preview/download behavior.
