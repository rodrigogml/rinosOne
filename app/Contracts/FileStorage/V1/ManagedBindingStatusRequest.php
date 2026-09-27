<?php

namespace App\Contracts\FileStorage\V1;

class ManagedBindingStatusRequest
{
    public function __construct(
        public readonly int $ownerId,
        public readonly string $bindingKey,
        public readonly string $purpose,
    ) {}
}
