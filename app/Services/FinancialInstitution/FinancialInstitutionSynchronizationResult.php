<?php

namespace App\Services\FinancialInstitution;

use Carbon\CarbonImmutable;

final readonly class FinancialInstitutionSynchronizationResult
{
    public function __construct(
        public bool $succeeded,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $finishedAt,
        public string $referenceDate,
        public int $createdCount,
        public int $updatedCount,
        public ?string $failureCode = null,
    ) {}
}
