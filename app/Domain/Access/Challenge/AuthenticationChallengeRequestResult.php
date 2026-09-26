<?php

namespace App\Domain\Access\Challenge;

use App\Domain\Access\RateLimiting\AccessRateLimitDecision;

final readonly class AuthenticationChallengeRequestResult
{
    public function __construct(
        public AccessRateLimitDecision $decision,
        public int $challengeId,
    ) {}
}
