<?php

namespace App\Services\EconomicIndicator;

/**
 * Immutable result of applying an index between two exact published reference dates.
 */
final readonly class EconomicIndicatorAdjustment
{
    public function __construct(
        public string $seriesCode,
        public string $startDate,
        public string $endDate,
        public string $inputValue,
        public string $updateFactor,
        public string $updateRatePercent,
        public string $updatedValue,
    ) {}
}
