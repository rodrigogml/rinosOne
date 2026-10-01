<?php

namespace App\Infrastructure\EconomicIndicator;

use Carbon\CarbonImmutable;

final readonly class EconomicPtaxSourceRecord
{
    public function __construct(public CarbonImmutable $referenceDate, public CarbonImmutable $quotedAt, public string $buyRate, public string $sellRate, public string $midRate, public string $sourceIdentity) {}
}
