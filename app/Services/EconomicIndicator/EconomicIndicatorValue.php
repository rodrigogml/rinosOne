<?php

namespace App\Services\EconomicIndicator;

use Carbon\CarbonInterface;

/**
 * Immutable value exposed to internal consumers of a current economic indicator.
 */
final readonly class EconomicIndicatorValue
{
    public function __construct(
        public string $seriesCode,
        public string $value,
        public ?string $accumulatedValue,
        public string $unit,
        public CarbonInterface $referenceDate,
        public ?CarbonInterface $publishedAt,
        public int $revision,
        public string $sourceIdentity,
    ) {}
}
