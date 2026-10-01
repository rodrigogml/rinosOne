<?php

namespace App\Infrastructure\EconomicIndicator;

use Carbon\CarbonImmutable;

final readonly class EconomicIndicatorSourceRecord
{
    public function __construct(public CarbonImmutable $referenceDate, public string $value, public string $sourceIdentity, public ?CarbonImmutable $publishedAt = null) {}
}
