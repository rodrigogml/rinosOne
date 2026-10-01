<?php

namespace App\Services\EconomicIndicator;

use App\Models\EconomicIndicatorObservation;
use App\Models\EconomicIndicatorSeries;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;

/**
 * Rebuilds deterministic index accumulations from the current published rates.
 *
 * Persisted accumulated values are projections for fast point queries only; they are never
 * used as inputs to a later rebuild, so source revisions and storage rounding cannot drift.
 */
final class EconomicIndicatorAccumulationService
{
    public const COMPOUND_PUBLISHED_RATE = 'COMPOUND_PUBLISHED_RATE';

    private const INTERNAL_SCALE = 36;

    private const STORED_SCALE = 18;

    public function recalculate(EconomicIndicatorSeries $series): int
    {
        if ($series->accumulationMode !== self::COMPOUND_PUBLISHED_RATE) {
            EconomicIndicatorObservation::query()
                ->where('idEconomicIndicatorSeries', $series->id)
                ->whereNotNull('currentKey')
                ->whereNotNull('accumulatedValue')
                ->update(['accumulatedValue' => null]);

            return 0;
        }

        $accumulated = '100';
        $first = true;
        $observations = EconomicIndicatorObservation::query()
            ->where('idEconomicIndicatorSeries', $series->id)
            ->whereNotNull('currentKey')
            ->orderBy('referenceDate')
            ->orderBy('id')
            ->get(['id', 'value', 'accumulatedValue']);

        foreach ($observations as $observation) {
            if (! $first) {
                $accumulated = bcmul(
                    $accumulated,
                    bcadd('1', bcdiv($observation->value, '100', self::INTERNAL_SCALE), self::INTERNAL_SCALE),
                    self::INTERNAL_SCALE,
                );
            }

            $stored = self::round($accumulated);
            if ($observation->accumulatedValue !== $stored) {
                $observation->update(['accumulatedValue' => $stored]);
            }
            $first = false;
        }

        return $observations->count();
    }

    public function adjustment(
        EconomicIndicatorSeries $series,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        string $inputValue,
    ): EconomicIndicatorAdjustment {
        $this->assertAccumulable($series);
        $this->assertFiniteDecimal($inputValue);
        if ($startDate->greaterThan($endDate)) {
            throw new InvalidArgumentException('The start date cannot be after the end date.');
        }

        $this->assertObservationExists($series, $startDate, 'startDate');
        $this->assertObservationExists($series, $endDate, 'endDate');

        $factor = '1';
        $rates = EconomicIndicatorObservation::query()
            ->where('idEconomicIndicatorSeries', $series->id)
            ->whereNotNull('currentKey')
            ->whereDate('referenceDate', '>', $startDate->toDateString())
            ->whereDate('referenceDate', '<=', $endDate->toDateString())
            ->orderBy('referenceDate')
            ->orderBy('id')
            ->pluck('value');

        foreach ($rates as $rate) {
            $factor = bcmul(
                $factor,
                bcadd('1', bcdiv($rate, '100', self::INTERNAL_SCALE), self::INTERNAL_SCALE),
                self::INTERNAL_SCALE,
            );
        }

        $roundedFactor = self::round($factor);
        $roundedInput = self::round($inputValue);

        return new EconomicIndicatorAdjustment(
            $series->code,
            $startDate->toDateString(),
            $endDate->toDateString(),
            $roundedInput,
            $roundedFactor,
            self::round(bcmul(bcsub($factor, '1', self::INTERNAL_SCALE + 1), '100', self::INTERNAL_SCALE)),
            self::round(bcmul($inputValue, $factor, self::INTERNAL_SCALE)),
        );
    }

    public static function round(string $value): string
    {
        $halfUnit = '0.'.str_repeat('0', self::STORED_SCALE).'5';
        $adjusted = bccomp($value, '0', self::INTERNAL_SCALE + 1) < 0
            ? bcsub($value, $halfUnit, self::INTERNAL_SCALE + 1)
            : bcadd($value, $halfUnit, self::INTERNAL_SCALE + 1);

        return bcadd($adjusted, '0', self::STORED_SCALE);
    }

    private function assertAccumulable(EconomicIndicatorSeries $series): void
    {
        if ($series->accumulationMode !== self::COMPOUND_PUBLISHED_RATE) {
            throw new DomainException('This index does not define a global accumulated series.');
        }
    }

    private function assertFiniteDecimal(string $value): void
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('The input value must be a finite decimal.');
        }
    }

    private function assertObservationExists(EconomicIndicatorSeries $series, CarbonInterface $date, string $parameter): void
    {
        $exists = EconomicIndicatorObservation::query()
            ->where('idEconomicIndicatorSeries', $series->id)
            ->whereDate('referenceDate', $date->toDateString())
            ->whereNotNull('currentKey')
            ->exists();

        if (! $exists) {
            throw new DomainException("The {$parameter} date has no published value for this index.");
        }
    }
}
