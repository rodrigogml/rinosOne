<?php

namespace App\Services\Locality;

final readonly class PostalReferenceRefreshState
{
    public function __construct(
        public string $state,
        public ?int $pollAfterMilliseconds,
    ) {}
}
