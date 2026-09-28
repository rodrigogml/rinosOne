<?php

namespace App\Infrastructure\Locality;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

interface PostalReferenceHttpSource extends PostalReferenceSource
{
    public function supports(string $countryCode, string $normalizedPostalCode): bool;

    public function enqueue(Pool $pool, string $countryCode, string $normalizedPostalCode): void;

    /** @return list<PostalReferenceSourceRecord> */
    public function recordsFromResponse(Response $response, string $countryCode, string $normalizedPostalCode): array;

    public function isNotFound(Response $response): bool;
}
