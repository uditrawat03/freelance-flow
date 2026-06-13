<?php

namespace App\Services;

use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Log;

class Logger
{
    /**
     * @var array<int, string>
     */
    private array $redactedKeys = [
        'authorization',
        'card_number',
        'cvv',
        'notes',
        'password',
        'password_confirmation',
        'secret',
        'stripe_payment_intent_id',
        'stripe_payment_status',
        'token',
    ];

    /**
     * Build the base context array attached to every log entry.
     * Includes workspace ID, user ID, and request metadata.
     */
    private function baseContext(): array
    {
        $context = [
            'environment' => app()->environment(),
        ];

        if (auth()->check()) {
            $context['user_id'] = auth()->id();
            $context['user_email'] = auth()->user()->email;
            $context['workspace_id'] = auth()->user()->currentWorkspace()?->id;
        }

        if (request()->exists('url')) {
            $context['url'] = request()->fullUrl();
            $context['method'] = request()->method();
            $context['ip'] = request()->ip();
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($this->shouldRedact((string) $key)) {
                $context[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->sanitize($value);
            }
        }

        return $context;
    }

    private function shouldRedact(string $key): bool
    {
        $normalizedKey = strtolower($key);

        return in_array($normalizedKey, $this->redactedKeys, true)
            || str_contains($normalizedKey, 'password')
            || str_contains($normalizedKey, 'token')
            || str_contains($normalizedKey, 'secret');
    }

    public function info(string $message, array $context = []): void
    {
        Log::info($message, $this->sanitize(array_merge($this->baseContext(), $context)));
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $this->sanitize(array_merge($this->baseContext(), $context)));
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, $this->sanitize(array_merge($this->baseContext(), $context)));
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, $this->sanitize(array_merge($this->baseContext(), $context)));
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $this->sanitize(array_merge($this->baseContext(), $context)));
    }

    // Log to a specific channel
    public function channel(string $channel): LogManager
    {
        return Log::channel($channel);
    }

    // Payment-specific logging to the dedicated payments channel
    public function payment(string $message, array $context = []): void
    {
        Log::channel('payments')->info($message, $this->sanitize(array_merge($this->baseContext(), $context)));
    }

    // Queue job logging to the dedicated queue channel
    public function queue(string $message, array $context = []): void
    {
        Log::channel('queue')->info($message, $this->sanitize(array_merge($this->baseContext(), $context)));
    }
}
