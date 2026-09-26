<?php

namespace App\Contracts\FileStorage\V1;

use Carbon\CarbonImmutable;

class FilePossessionLifecycleResult
{
    public function __construct(
        public readonly int $possessionId,
        public readonly string $state,
        public readonly ?CarbonImmutable $purgeAfter,
        public readonly ?CarbonImmutable $releasedAt,
    ) {}
}
