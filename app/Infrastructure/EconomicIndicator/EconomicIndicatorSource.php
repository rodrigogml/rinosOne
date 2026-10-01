<?php

namespace App\Infrastructure\EconomicIndicator;

use App\Models\EconomicIndicatorSeries;
use Carbon\CarbonInterface;

interface EconomicIndicatorSource
{
    /** @return list<EconomicIndicatorSourceRecord> */
    public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array;
}
