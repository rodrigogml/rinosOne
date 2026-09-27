<?php

namespace App\Contracts\FileStorage\V1;

use Carbon\CarbonImmutable;

class ManagedBindingStatus
{
    public function __construct(
        public readonly bool $available,
        public readonly ?CarbonImmutable $updatedAt,
    ) {}
}
