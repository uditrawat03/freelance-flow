<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class Logger
{
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

    public function info(string $message, array $context = []): void
    {
        Log::info($message, array_merge($this->baseContext(), $context));
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge($this->baseContext(), $context));
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, array_merge($this->baseContext(), $context));
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, array_merge($this->baseContext(), $context));
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, array_merge($this->baseContext(), $context));
    }

    // Log to a specific channel
    public function channel(string $channel): \Illuminate\Log\LogManager
    {
        return Log::channel($channel);
    }

    // Payment-specific logging to the dedicated payments channel
    public function payment(string $message, array $context = []): void
    {
        Log::channel('payments')->info($message, array_merge($this->baseContext(), $context));
    }

    // Queue job logging to the dedicated queue channel
    public function queue(string $message, array $context = []): void
    {
        Log::channel('queue')->info($message, array_merge($this->baseContext(), $context));
    }
}