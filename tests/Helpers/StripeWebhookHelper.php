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

    public static function paymentSucceeded(
        string $paymentIntentId,
        int $amount,
        array $metadata = [],
    ): array {
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
