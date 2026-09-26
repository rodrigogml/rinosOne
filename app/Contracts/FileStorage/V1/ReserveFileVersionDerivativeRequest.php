<?php

namespace App\Contracts\FileStorage\V1;

class ReserveFileVersionDerivativeRequest
{
    public function __construct(
        public readonly int $sourceVersionId,
        public readonly int $derivativeContentId,
        public readonly string $derivativeKind,
        public readonly string $derivativeKey,
        public readonly string $state = 'PENDING',
    ) {}
}
