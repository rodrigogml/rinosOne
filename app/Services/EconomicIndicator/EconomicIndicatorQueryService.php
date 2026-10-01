<?php

namespace App\Services\EconomicIndicator;

use App\Models\EconomicIndicatorObservation;
use App\Models\EconomicIndicatorSeries;
use App\Models\EconomicPtaxQuote;
use Carbon\CarbonInterface;

/**
 * Supplies current, global economic reference data to internal application services.
 *
 * Consumers use this service instead of querying the persistence tables directly.
 */
final class EconomicIndicatorQueryService
{
    public function __construct(private readonly EconomicIndicatorAccumulationService $accumulation) {}

    public function indicator(string $seriesCode, CarbonInterface $referenceDate): ?EconomicIndicatorValue
    {
        $observation = EconomicIndicatorObservation::query()
            ->with('series')
            ->whereHas('series', fn ($query) => $query->where('code', $seriesCode)->where('kind', 'INDEX')->where('active', true))
            ->whereDate('referenceDate', $referenceDate->toDateString())
            ->whereNotNull('currentKey')
            ->first();

        return $observation === null ? null : new EconomicIndicatorValue(
            $observation->series->code,
            $observation->value,
            $observation->accumulatedValue,
            $observation->series->unit,
            $observation->referenceDate,
            $observation->publishedAt,
            $observation->revision,
            $observation->sourceIdentity,
        );
    }

    public function adjustment(
        string $seriesCode,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        string $inputValue,
    ): EconomicIndicatorAdjustment {
        $series = $this->activeIndexSeries($seriesCode);

        return $this->accumulation->adjustment($series, $startDate, $endDate, $inputValue);
    }

    private function activeIndexSeries(string $seriesCode): EconomicIndicatorSeries
    {
        $series = EconomicIndicatorSeries::query()
            ->where('code', $seriesCode)
            ->where('kind', 'INDEX')
            ->where('active', true)
            ->first();

        if ($series === null) {
            throw new \InvalidArgumentException('The requested active index series was not found.');
        }

        return $series;
    }

    public function ptax(string $currencyCode, CarbonInterface $referenceDate): ?EconomicPtaxValue
    {
        $quote = EconomicPtaxQuote::query()
            ->with('series')
            ->whereHas('series', fn ($query) => $query->where('code', 'PTAX_'.strtoupper($currencyCode).'_BRL')->where('kind', 'PTAX')->where('active', true))
            ->whereDate('referenceDate', $referenceDate->toDateString())
            ->whereNotNull('currentKey')
            ->first();

        return $quote === null ? null : new EconomicPtaxValue(
            strtoupper($currencyCode),
            $quote->buyRate,
            $quote->sellRate,
            $quote->midRate,
            $quote->referenceDate,
            $quote->quotedAt,
            $quote->revision,
            $quote->sourceIdentity,
        );
    }
}
