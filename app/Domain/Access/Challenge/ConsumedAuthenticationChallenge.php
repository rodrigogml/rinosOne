<?php

namespace App\Domain\Access\Challenge;

final readonly class ConsumedAuthenticationChallenge
{
    public function __construct(
        public string $challengeId,
        public string $userId,
        public AuthenticationChallengePurpose $purpose,
        public bool $rememberMeRequested,
    ) {}
}
