<?php

namespace App\Domain\Access\Challenge;

use DateTimeInterface;

final readonly class IssuedAuthenticationChallenge
{
    public function __construct(
        public int $challengeId,
        public string $linkToken,
        public string $code,
        public DateTimeInterface $expiresAt,
    ) {}
}
