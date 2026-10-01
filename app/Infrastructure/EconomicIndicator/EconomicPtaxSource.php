<?php

namespace App\Infrastructure\EconomicIndicator;

use App\Models\EconomicIndicatorSeries;
use Carbon\CarbonInterface;

interface EconomicPtaxSource
{
    /** @return list<EconomicPtaxSourceRecord> */
    public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array;
}
