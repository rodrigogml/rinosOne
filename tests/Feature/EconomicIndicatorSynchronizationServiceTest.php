<?php

namespace Tests\Feature;

use App\Infrastructure\EconomicIndicator\EconomicIndicatorSource;
use App\Infrastructure\EconomicIndicator\EconomicIndicatorSourceRecord;
use App\Infrastructure\EconomicIndicator\EconomicPtaxSource;
use App\Infrastructure\EconomicIndicator\EconomicPtaxSourceRecord;
use App\Models\EconomicIndicatorObservation;
use App\Models\EconomicIndicatorSeries;
use App\Models\EconomicPtaxQuote;
use App\Services\EconomicIndicator\EconomicIndicatorQueryService;
use App\Services\EconomicIndicator\EconomicIndicatorSynchronizationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Tests\TestCase;

class EconomicIndicatorSynchronizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_synchronization_seeds_catalog_is_idempotent_and_preserves_a_revision(): void
    {
        config()->set('economic-indicators.historical_chunk_days', 50_000);
        $now = CarbonImmutable::parse('2026-10-01 12:00:00', 'America/Sao_Paulo');
        $first = new EconomicIndicatorSynchronizationService($this->indicatorSource('1.000000000000'), $this->ptaxSource());

        $firstResult = $first->synchronize($now);
        $repeatedResult = $first->synchronize($now);
        $revisedResult = (new EconomicIndicatorSynchronizationService($this->indicatorSource('1.500000000000'), $this->ptaxSource()))
            ->synchronize($now);

        $this->assertTrue($firstResult->succeeded);
        $this->assertSame(7, EconomicIndicatorSeries::query()->count());
        $this->assertSame(7, $firstResult->createdCount);
        $this->assertSame(2, EconomicPtaxQuote::query()->count());
        $this->assertSame(0, $repeatedResult->createdCount);
        $this->assertSame(0, $repeatedResult->revisedCount);
        $this->assertTrue($revisedResult->succeeded, $revisedResult->failureCode ?? 'Unknown synchronization failure.');
        $this->assertSame(1, $revisedResult->revisedCount);
        $this->assertSame(6, EconomicIndicatorObservation::query()->count());

        $current = app(EconomicIndicatorQueryService::class)->indicator('SELIC_DAILY', $now);
        $ptax = app(EconomicIndicatorQueryService::class)->ptax('USD', $now);

        $this->assertSame('1.500000000000', $current?->value);
        $this->assertSame(2, $current?->revision);
        $this->assertSame('5.000000000000', $ptax?->buyRate);
        $this->assertSame('5.100000000000', $ptax?->sellRate);
        $this->assertSame('5.050000000000', $ptax?->midRate);
    }

    public function test_first_synchronization_reads_the_complete_history_in_bounded_windows(): void
    {
        config()->set('economic-indicators.historical_chunk_days', 365);
        $indicatorSource = new class implements EconomicIndicatorSource
        {
            /** @var list<array{seriesCode: string, from: string, to: string}> */
            public array $calls = [];

            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                $this->calls[] = [
                    'seriesCode' => $series->code,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ];

                return [new EconomicIndicatorSourceRecord(
                    CarbonImmutable::instance($from)->startOfDay(),
                    '0.100000000000',
                    'test:'.$series->code.':'.$from->toDateString(),
                )];
            }
        };
        $ptaxSource = new class implements EconomicPtaxSource
        {
            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                return [];
            }
        };

        (new EconomicIndicatorSynchronizationService($indicatorSource, $ptaxSource))
            ->synchronize(CarbonImmutable::parse('1987-06-05', 'America/Sao_Paulo'));

        $selicCalls = array_values(array_filter(
            $indicatorSource->calls,
            static fn (array $call): bool => $call['seriesCode'] === 'SELIC_DAILY',
        ));

        $this->assertCount(2, $selicCalls);
        $this->assertSame('1986-06-04', $selicCalls[0]['from']);
        $this->assertSame('1987-06-04', $selicCalls[1]['from']);
        $this->assertSame('1987-06-05', $selicCalls[1]['to']);
        $this->assertSame('1986-06-04', EconomicIndicatorSeries::query()->where('code', 'SELIC_DAILY')->sole()->firstReferenceDate?->toDateString());
    }

    public function test_a_source_failure_rolls_back_the_whole_synchronization(): void
    {
        $result = (new EconomicIndicatorSynchronizationService($this->failingIndicatorSource(), $this->ptaxSource()))
            ->synchronize(CarbonImmutable::parse('2026-10-01', 'America/Sao_Paulo'));

        $this->assertFalse($result->succeeded);
        $this->assertSame(RuntimeException::class, $result->failureCode);
        $this->assertSame(RuntimeException::class, $result->series['SELIC_DAILY']['failureCode']);
        $this->assertSame('BCB_SGS', $result->series['SELIC_DAILY']['sourceKey']);
        $this->assertSame(0, EconomicIndicatorSeries::query()->count());
        $this->assertSame(0, EconomicIndicatorObservation::query()->count());
    }

    public function test_synchronization_can_be_limited_to_an_explicit_catalog_series(): void
    {
        config()->set('economic-indicators.enabled_series', ['SELIC_DAILY']);
        config()->set('economic-indicators.historical_chunk_days', 50_000);

        $result = (new EconomicIndicatorSynchronizationService($this->indicatorSource('1.000000000000'), $this->ptaxSource()))
            ->synchronize(CarbonImmutable::parse('2026-10-01', 'America/Sao_Paulo'));

        $this->assertTrue($result->succeeded);
        $this->assertSame(1, $result->createdCount);
        $this->assertSame(1, EconomicIndicatorSeries::query()->count());
        $this->assertSame('SELIC_DAILY', EconomicIndicatorSeries::sole()->code);
        $this->assertSame(1, EconomicIndicatorObservation::query()->count());
        $this->assertSame(0, EconomicPtaxQuote::query()->count());
    }

    public function test_repeat_does_not_create_a_revision_when_a_source_decimal_has_a_different_scale(): void
    {
        config()->set('economic-indicators.enabled_series', ['SELIC_DAILY', 'PTAX_USD_BRL']);
        config()->set('economic-indicators.historical_chunk_days', 50_000);
        $now = CarbonImmutable::parse('2026-10-01', 'America/Sao_Paulo');
        $synchronization = new EconomicIndicatorSynchronizationService(
            $this->indicatorSource('0.050788'),
            $this->ptaxSource('5', '5.1', '5.05'),
        );

        $first = $synchronization->synchronize($now);
        EconomicIndicatorObservation::sole()->update(['revision' => 2]);
        EconomicPtaxQuote::sole()->update(['revision' => 2]);
        $repeated = $synchronization->synchronize($now);

        $this->assertTrue($first->succeeded);
        $this->assertSame(0, $repeated->createdCount);
        $this->assertSame(0, $repeated->revisedCount);
        $this->assertSame(1, EconomicIndicatorObservation::query()->count());
        $this->assertSame(1, EconomicIndicatorObservation::sole()->revision);
        $this->assertSame(1, EconomicPtaxQuote::query()->count());
        $this->assertSame(1, EconomicPtaxQuote::sole()->revision);
    }

    public function test_initial_history_automatically_splits_a_window_when_the_source_connection_fails(): void
    {
        config()->set('economic-indicators.enabled_series', ['SELIC_DAILY']);
        config()->set('economic-indicators.historical_chunk_days', 9);
        $indicatorSource = new class implements EconomicIndicatorSource
        {
            /** @var list<array{from: string, to: string}> */
            public array $calls = [];

            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                $this->calls[] = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
                if ($from->diffInDays($to) > 2) {
                    throw new ConnectionException('The source window is too large.');
                }

                return [new EconomicIndicatorSourceRecord(
                    CarbonImmutable::instance($to)->startOfDay(),
                    '0.100000000000',
                    'test:'.$to->toDateString(),
                )];
            }
        };
        $ptaxSource = new class implements EconomicPtaxSource
        {
            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                return [];
            }
        };

        $result = (new EconomicIndicatorSynchronizationService($indicatorSource, $ptaxSource))
            ->synchronize(CarbonImmutable::parse('1986-06-12', 'America/Sao_Paulo'));

        $this->assertTrue($result->succeeded);
        $this->assertGreaterThan(1, count($indicatorSource->calls));
        $this->assertSame('1986-06-04', $indicatorSource->calls[0]['from']);
        $this->assertSame('1986-06-12', $indicatorSource->calls[0]['to']);
        $this->assertSame('1986-06-06', EconomicIndicatorSeries::sole()->firstReferenceDate?->toDateString());
    }

    public function test_accumulations_are_rebuilt_from_current_raw_values_after_every_update(): void
    {
        config()->set('economic-indicators.enabled_series', ['SELIC_DAILY']);
        config()->set('economic-indicators.historical_chunk_days', 50_000);
        $now = CarbonImmutable::parse('1986-06-06', 'America/Sao_Paulo');

        $this->synchronizeIndicatorRecords([
            ['1986-06-04', '10.000000000000'],
            ['1986-06-05', '5.000000000000'],
            ['1986-06-06', '-2.000000000000'],
        ], $now);

        $firstRun = EconomicIndicatorObservation::query()
            ->whereNotNull('currentKey')
            ->orderBy('referenceDate')
            ->pluck('accumulatedValue')
            ->all();
        $this->assertSame(['100.000000000000000000', '105.000000000000000000', '102.900000000000000000'], $firstRun);

        $this->synchronizeIndicatorRecords([
            ['1986-06-04', '10.000000000000'],
            ['1986-06-05', '7.000000000000'],
            ['1986-06-06', '-2.000000000000'],
        ], $now);

        $current = EconomicIndicatorObservation::query()
            ->whereNotNull('currentKey')
            ->orderBy('referenceDate')
            ->pluck('accumulatedValue')
            ->all();
        $this->assertSame(['100.000000000000000000', '107.000000000000000000', '104.860000000000000000'], $current);
        $this->assertNull(EconomicIndicatorObservation::query()->where('value', '5.000000000000')->sole()->accumulatedValue);

        EconomicIndicatorObservation::query()->whereNotNull('currentKey')->orderBy('referenceDate')->firstOrFail()
            ->update(['accumulatedValue' => '999.000000000000000000']);
        $this->synchronizeIndicatorRecords([
            ['1986-06-04', '10.000000000000'],
            ['1986-06-05', '7.000000000000'],
            ['1986-06-06', '-2.000000000000'],
        ], $now);

        $this->assertSame('100.000000000000000000', EconomicIndicatorObservation::query()
            ->whereNotNull('currentKey')->orderBy('referenceDate')->firstOrFail()->accumulatedValue);

        $adjustment = app(EconomicIndicatorQueryService::class)->adjustment(
            'SELIC_DAILY',
            CarbonImmutable::parse('1986-06-04'),
            CarbonImmutable::parse('1986-06-06'),
            '100.000000000000000000',
        );
        $this->assertSame('1.048600000000000000', $adjustment->updateFactor);
        $this->assertSame('4.860000000000000000', $adjustment->updateRatePercent);
        $this->assertSame('104.860000000000000000', $adjustment->updatedValue);
    }

    public function test_savings_return_is_not_composed_as_a_global_accumulation(): void
    {
        config()->set('economic-indicators.enabled_series', ['SAVINGS_RETURN_DAILY']);
        config()->set('economic-indicators.historical_chunk_days', 50_000);
        $this->synchronizeIndicatorRecords([['2012-05-04', '0.500000000000']], CarbonImmutable::parse('2012-05-04', 'America/Sao_Paulo'));

        $observation = EconomicIndicatorObservation::sole();
        $this->assertSame('NO_GLOBAL_ACCUMULATION', $observation->series()->sole()->accumulationMode);
        $this->assertNull($observation->accumulatedValue);
        $this->expectException(DomainException::class);

        app(EconomicIndicatorQueryService::class)->adjustment(
            'SAVINGS_RETURN_DAILY',
            CarbonImmutable::parse('2012-05-04'),
            CarbonImmutable::parse('2012-05-04'),
            '100',
        );
    }

    /** @param list<array{string, string}> $records */
    private function synchronizeIndicatorRecords(array $records, CarbonImmutable $now): void
    {
        $source = new class($records) implements EconomicIndicatorSource
        {
            /** @param list<array{string, string}> $records */
            public function __construct(private readonly array $records) {}

            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                return array_map(
                    static fn (array $record): EconomicIndicatorSourceRecord => new EconomicIndicatorSourceRecord(
                        CarbonImmutable::parse($record[0], 'America/Sao_Paulo'),
                        $record[1],
                        'test:'.$series->code.':'.$record[0].':'.$record[1],
                    ),
                    $this->records,
                );
            }
        };
        $ptaxSource = new class implements EconomicPtaxSource
        {
            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                return [];
            }
        };

        $result = (new EconomicIndicatorSynchronizationService($source, $ptaxSource))->synchronize($now);
        $this->assertTrue($result->succeeded, $result->failureCode ?? 'Unknown synchronization failure.');
    }

    private function indicatorSource(string $selicValue): EconomicIndicatorSource
    {
        return new class($selicValue) implements EconomicIndicatorSource
        {
            public function __construct(private readonly string $selicValue) {}

            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                $value = $series->code === 'SELIC_DAILY' ? $this->selicValue : '0.250000000000';

                return [new EconomicIndicatorSourceRecord(
                    CarbonImmutable::instance($to)->startOfDay(),
                    $value,
                    'test:'.$series->code.':'.$value,
                )];
            }
        };
    }

    private function ptaxSource(
        string $buyRate = '5.000000000000',
        string $sellRate = '5.100000000000',
        string $midRate = '5.050000000000',
    ): EconomicPtaxSource {
        return new class($buyRate, $sellRate, $midRate) implements EconomicPtaxSource
        {
            public function __construct(
                private readonly string $buyRate,
                private readonly string $sellRate,
                private readonly string $midRate,
            ) {}

            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                return [new EconomicPtaxSourceRecord(
                    CarbonImmutable::instance($to)->startOfDay(),
                    CarbonImmutable::instance($to),
                    $this->buyRate,
                    $this->sellRate,
                    $this->midRate,
                    'test:'.$series->code.':'.$this->buyRate.':'.$this->sellRate.':'.$this->midRate,
                )];
            }
        };
    }

    private function failingIndicatorSource(): EconomicIndicatorSource
    {
        return new class implements EconomicIndicatorSource
        {
            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                throw new RuntimeException('BCB unavailable');
            }
        };
    }
}
