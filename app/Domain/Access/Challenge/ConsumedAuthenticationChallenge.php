<?php

namespace App\Domain\Access\Challenge;

final readonly class ConsumedAuthenticationChallenge
{
    public function __construct(
        public int $challengeId,
        public int $userId,
        public AuthenticationChallengePurpose $purpose,
        public bool $rememberMeRequested,
    ) {}
}
