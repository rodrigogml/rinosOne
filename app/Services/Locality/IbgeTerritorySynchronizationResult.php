<?php

namespace App\Services\Locality;

use Carbon\CarbonInterface;

final readonly class IbgeTerritorySynchronizationResult
{
    public function __construct(
        public bool $succeeded,
        public CarbonInterface $startedAt,
        public CarbonInterface $finishedAt,
        public int $createdCountryCount,
        public int $updatedCountryCount,
        public int $createdStateCount,
        public int $updatedStateCount,
        public int $createdMunicipalityCount,
        public int $updatedMunicipalityCount,
        public ?string $failureCode = null,
    ) {}
}
