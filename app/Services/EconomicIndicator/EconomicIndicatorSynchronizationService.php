<?php

namespace App\Services\EconomicIndicator;

use App\Infrastructure\EconomicIndicator\EconomicIndicatorSource;
use App\Infrastructure\EconomicIndicator\EconomicIndicatorSourceRecord;
use App\Infrastructure\EconomicIndicator\EconomicPtaxSource;
use App\Infrastructure\EconomicIndicator\EconomicPtaxSourceRecord;
use App\Models\EconomicIndicatorObservation;
use App\Models\EconomicIndicatorSeries;
use App\Models\EconomicPtaxQuote;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnexpectedValueException;

final class EconomicIndicatorSynchronizationService
{
    public function __construct(
        private readonly EconomicIndicatorSource $indicatorSource,
        private readonly EconomicPtaxSource $ptaxSource,
        ?EconomicIndicatorAccumulationService $accumulation = null,
    ) {
        $this->accumulation = $accumulation ?? new EconomicIndicatorAccumulationService;
    }

    private readonly EconomicIndicatorAccumulationService $accumulation;

    public function synchronize(?CarbonInterface $now = null): EconomicIndicatorSynchronizationResult
    {
        $startedAt = CarbonImmutable::instance($now ?? CarbonImmutable::now('America/Sao_Paulo'));
        try {
            $loadedSeries = $this->loadSourceRecords($startedAt);

            [$created, $revised, $seriesResults] = DB::transaction(function () use ($loadedSeries, $startedAt): array {
                $created = $revised = 0;
                $seriesResults = [];
                foreach ($loadedSeries as ['definition' => $definition, 'from' => $from, 'through' => $through, 'records' => $records]) {
                    $series = EconomicIndicatorSeries::query()->updateOrCreate(
                        ['code' => $definition['code']],
                        $this->seriesAttributes($definition),
                    );
                    $seriesCreated = $seriesRevised = 0;
                    foreach ($records as $record) {
                        [$wasCreated, $wasRevised] = $series->kind === 'PTAX'
                            ? $this->storePtax($series, $record, $startedAt)
                            : $this->storeObservation($series, $record, $startedAt);
                        $created += $wasCreated ? 1 : 0;
                        $revised += $wasRevised ? 1 : 0;
                        $seriesCreated += $wasCreated ? 1 : 0;
                        $seriesRevised += $wasRevised ? 1 : 0;
                    }
                    $this->recordFirstReferenceDate($series, $records);
                    $recalculated = $series->kind === 'INDEX' ? $this->accumulation->recalculate($series) : 0;
                    $seriesResults[$series->code] = [
                        'sourceKey' => $series->sourceKey,
                        'from' => $from,
                        'through' => $through,
                        'createdCount' => $seriesCreated,
                        'revisedCount' => $seriesRevised,
                        'recalculatedCount' => $recalculated,
                    ];
                }

                return [$created, $revised, $seriesResults];
            });

            return new EconomicIndicatorSynchronizationResult(true, $startedAt, CarbonImmutable::now('America/Sao_Paulo'), $created, $revised, series: $seriesResults);
        } catch (EconomicIndicatorSourceLoadException $error) {
            $cause = $error->getPrevious() ?? $error;

            return new EconomicIndicatorSynchronizationResult(
                false,
                $startedAt,
                CarbonImmutable::now('America/Sao_Paulo'),
                0,
                0,
                $cause::class,
                [$error->seriesCode => [
                    'sourceKey' => $error->sourceKey,
                    'from' => $error->from,
                    'through' => $error->through,
                    'failureCode' => $cause::class,
                ]],
            );
        } catch (Throwable $error) {
            return new EconomicIndicatorSynchronizationResult(false, $startedAt, CarbonImmutable::now('America/Sao_Paulo'), 0, 0, $error::class);
        }
    }

    /**
     * Downloads source records before opening the persistence transaction.
     *
     * @return list<array{definition: array{code: string, kind: string, name: string, sourceKey: string, sourceSeriesCode: string, periodicity: string, unit: string, accumulationMode: string, initialReferenceDate: string}, from: string, through: string, records: list<EconomicIndicatorSourceRecord|EconomicPtaxSourceRecord>}>
     */
    private function loadSourceRecords(CarbonImmutable $through): array
    {
        $loaded = [];
        foreach (EconomicIndicatorCatalog::all() as $definition) {
            $existing = EconomicIndicatorSeries::query()->where('code', $definition['code'])->first();
            $series = $existing ?? new EconomicIndicatorSeries($this->seriesAttributes($definition));
            $initialLoad = $existing === null || $existing->firstReferenceDate === null;
            $from = $initialLoad
                ? CarbonImmutable::parse($definition['initialReferenceDate'], 'America/Sao_Paulo')->startOfDay()
                : $through->subDays(max(1, (int) config('economic-indicators.overlap_days')))->startOfDay();
            $records = [];

            try {
                foreach ($this->windows($from, $through, $initialLoad) as [$windowStart, $windowEnd]) {
                    $windowRecords = $this->loadWindowRecords($definition, $series, $windowStart, $windowEnd, $initialLoad);
                    foreach ($windowRecords as $record) {
                        if ($definition['kind'] === 'PTAX' && ! $record instanceof EconomicPtaxSourceRecord) {
                            throw new UnexpectedValueException('PTAX source returned an invalid record type.');
                        }
                        if ($definition['kind'] === 'INDEX' && ! $record instanceof EconomicIndicatorSourceRecord) {
                            throw new UnexpectedValueException('Economic indicator source returned an invalid record type.');
                        }
                        $records[] = $record;
                    }
                }
            } catch (Throwable $error) {
                throw new EconomicIndicatorSourceLoadException(
                    $definition['code'],
                    $definition['sourceKey'],
                    $from->toDateString(),
                    $through->toDateString(),
                    $error,
                );
            }

            $loaded[] = [
                'definition' => $definition,
                'from' => $from->toDateString(),
                'through' => $through->toDateString(),
                'records' => $records,
            ];
        }

        return $loaded;
    }

    /** @return list<EconomicIndicatorSourceRecord|EconomicPtaxSourceRecord> */
    private function loadWindowRecords(
        array $definition,
        EconomicIndicatorSeries $series,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $historical,
    ): array {
        try {
            return $definition['kind'] === 'PTAX'
                ? $this->ptaxSource->fetch($series, $from, $to)
                : $this->indicatorSource->fetch($series, $from, $to);
        } catch (ConnectionException $exception) {
            if (! $historical || $from->isSameDay($to)) {
                throw $exception;
            }

            $days = $from->diffInDays($to);
            $splitEnd = $from->addDays(intdiv($days, 2))->startOfDay();

            return [
                ...$this->loadWindowRecords($definition, $series, $from, $splitEnd, true),
                ...$this->loadWindowRecords($definition, $series, $splitEnd->addDay(), $to, true),
            ];
        }
    }

    /** @return list<array{CarbonImmutable, CarbonImmutable}> */
    private function windows(CarbonImmutable $from, CarbonImmutable $through, bool $historical): array
    {
        if (! $historical) {
            return [[$from, $through]];
        }

        $chunkDays = max(1, (int) config('economic-indicators.historical_chunk_days'));
        $windows = [];
        $cursor = $from;
        while ($cursor->lessThanOrEqualTo($through)) {
            $end = $cursor->addDays($chunkDays - 1)->min($through);
            $windows[] = [$cursor, $end];
            $cursor = $end->addDay()->startOfDay();
        }

        return $windows;
    }

    /** @param array{code: string, kind: string, name: string, sourceKey: string, sourceSeriesCode: string, periodicity: string, unit: string, accumulationMode: string, initialReferenceDate: string} $definition */
    private function seriesAttributes(array $definition): array
    {
        unset($definition['initialReferenceDate']);

        return $definition + ['active' => true];
    }

    /** @param list<EconomicIndicatorSourceRecord|EconomicPtaxSourceRecord> $records */
    private function recordFirstReferenceDate(EconomicIndicatorSeries $series, array $records): void
    {
        if ($records === []) {
            return;
        }

        $earliest = min(array_map(static fn (EconomicIndicatorSourceRecord|EconomicPtaxSourceRecord $record): string => $record->referenceDate->toDateString(), $records));
        if ($series->firstReferenceDate === null || $earliest < $series->firstReferenceDate->toDateString()) {
            $series->update(['firstReferenceDate' => $earliest]);
        }
    }

    /** @return array{bool, bool} */
    private function storeObservation(EconomicIndicatorSeries $series, EconomicIndicatorSourceRecord $record, CarbonImmutable $capturedAt): array
    {
        $key = $series->id.':'.$record->referenceDate->toDateString();
        $current = EconomicIndicatorObservation::query()->where('currentKey', $key)->first();
        if ($current !== null && $this->decimalEquals($current->value, $record->value)) {
            $this->repairScaleOnlyRevision($current);

            return [false, false];
        }
        if ($current !== null) {
            $current->update(['currentKey' => null, 'accumulatedValue' => null]);
        }
        $revision = ((int) EconomicIndicatorObservation::query()
            ->where('idEconomicIndicatorSeries', $series->id)
            ->whereDate('referenceDate', $record->referenceDate->toDateString())
            ->max('revision')) + 1;
        $candidate = EconomicIndicatorObservation::query()->firstOrNew([
            'idEconomicIndicatorSeries' => $series->id,
            'sourceIdentity' => $record->sourceIdentity,
        ]);
        $created = ! $candidate->exists;
        $candidate->fill([
            'referenceDate' => $record->referenceDate,
            'value' => $record->value,
            'revision' => $revision,
            'currentKey' => $key,
            'publishedAt' => $record->publishedAt,
            'capturedAt' => $capturedAt,
        ])->save();

        $revised = $current !== null || $revision > 1;

        return [$created && ! $revised, $revised];
    }

    /** @return array{bool, bool} */
    private function storePtax(EconomicIndicatorSeries $series, EconomicPtaxSourceRecord $record, CarbonImmutable $capturedAt): array
    {
        $key = $series->id.':'.$record->referenceDate->toDateString();
        $current = EconomicPtaxQuote::query()->where('currentKey', $key)->first();
        if ($current !== null
            && $this->decimalEquals($current->buyRate, $record->buyRate)
            && $this->decimalEquals($current->sellRate, $record->sellRate)
            && $this->decimalEquals($current->midRate, $record->midRate)) {
            $this->repairScaleOnlyRevision($current);

            return [false, false];
        }
        if ($current !== null) {
            $current->update(['currentKey' => null]);
        }
        $revision = ((int) EconomicPtaxQuote::query()
            ->where('idEconomicIndicatorSeries', $series->id)
            ->whereDate('referenceDate', $record->referenceDate->toDateString())
            ->max('revision')) + 1;
        $candidate = EconomicPtaxQuote::query()->firstOrNew([
            'idEconomicIndicatorSeries' => $series->id,
            'sourceIdentity' => $record->sourceIdentity,
        ]);
        $created = ! $candidate->exists;
        $candidate->fill([
            'referenceDate' => $record->referenceDate,
            'quotedAt' => $record->quotedAt,
            'buyRate' => $record->buyRate,
            'sellRate' => $record->sellRate,
            'midRate' => $record->midRate,
            'revision' => $revision,
            'currentKey' => $key,
            'capturedAt' => $capturedAt,
        ])->save();

        $revised = $current !== null || $revision > 1;

        return [$created && ! $revised, $revised];
    }

    private function decimalEquals(string $stored, string $received): bool
    {
        return bccomp($stored, $received, 12) === 0;
    }

    private function repairScaleOnlyRevision(EconomicIndicatorObservation|EconomicPtaxQuote $current): void
    {
        if ($current->revision <= 1) {
            return;
        }

        $query = $current instanceof EconomicIndicatorObservation
            ? EconomicIndicatorObservation::query()
            : EconomicPtaxQuote::query();
        $versionCount = $query
            ->where('idEconomicIndicatorSeries', $current->idEconomicIndicatorSeries)
            ->whereDate('referenceDate', $current->referenceDate->toDateString())
            ->count();

        if ($versionCount === 1) {
            $current->update(['revision' => 1]);
        }
    }
}
