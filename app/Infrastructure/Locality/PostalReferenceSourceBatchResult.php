<?php

namespace App\Infrastructure\Locality;

final readonly class PostalReferenceSourceBatchResult
{
    /** @param list<PostalReferenceSourceFetchResult> $sources */
    public function __construct(public array $sources) {}

    /** @return list<PostalReferenceSourceRecord> */
    public function records(): array
    {
        if ($this->sources === []) {
            return [];
        }

        return array_merge(...array_map(static fn (PostalReferenceSourceFetchResult $result): array => $result->records, $this->sources));
    }

    public function hasFailures(): bool
    {
        foreach ($this->sources as $source) {
            if (! $source->succeeded) {
                return true;
            }
        }

        return false;
    }
}
