<?php

namespace App\Infrastructure\Locality;

final readonly class PostalReferenceSourceFetchResult
{
    /** @param list<PostalReferenceSourceRecord> $records */
    public function __construct(
        public string $sourceKey,
        public bool $succeeded,
        public array $records,
    ) {}
}
