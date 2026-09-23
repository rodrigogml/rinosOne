<?php

namespace App\Domain\Access\RateLimiting;

final readonly class AccessRateLimitDecision
{
    private function __construct(
        public bool $allowed,
        public int $retryAfterSeconds,
    ) {}

    public static function allow(): self
    {
        return new self(true, 0);
    }

    public static function deny(int $retryAfterSeconds): self
    {
        return new self(false, max(1, $retryAfterSeconds));
    }
}
