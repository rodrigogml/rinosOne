<?php

namespace App\Services\EconomicIndicator;

use Carbon\CarbonImmutable;

final readonly class EconomicIndicatorSynchronizationResult
{
    /**
     * @param  array<string, array{sourceKey: string, from: string, through: string, createdCount?: int, revisedCount?: int, recalculatedCount?: int, failureCode?: string}>  $series
     */
    public function __construct(
        public bool $succeeded,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $finishedAt,
        public int $createdCount,
        public int $revisedCount,
        public ?string $failureCode = null,
        public array $series = [],
    ) {}
}
