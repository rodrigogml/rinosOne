<?php

namespace App\Infrastructure\Locality;

interface PostalReferenceSource
{
    public function sourceKey(): string;

    public function enabled(): bool;

    /** @return list<PostalReferenceSourceRecord> */
    public function fetch(string $countryCode, string $normalizedPostalCode): array;
}
