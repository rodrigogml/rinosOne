<?php

namespace App\Services\EconomicIndicator;

use Carbon\CarbonInterface;

/**
 * Immutable PTAX closing value exposed to internal consumers.
 */
final readonly class EconomicPtaxValue
{
    public function __construct(
        public string $currencyCode,
        public string $buyRate,
        public string $sellRate,
        public string $midRate,
        public CarbonInterface $referenceDate,
        public CarbonInterface $quotedAt,
        public int $revision,
        public string $sourceIdentity,
    ) {}
}
