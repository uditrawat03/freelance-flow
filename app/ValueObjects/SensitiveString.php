<?php

namespace App\ValueObjects;

use JsonSerializable;
use Stringable;

class SensitiveString implements JsonSerializable, Stringable
{
    public function __construct(
        private readonly string $value,
    ) {}

    public static function from(?string $value): ?self
    {
        return $value === null ? null : new self($value);
    }

    public function reveal(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return '[REDACTED]';
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    public function jsonSerialize(): string
    {
        return '[REDACTED]';
    }
}
