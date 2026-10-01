<?php

namespace App\Services\EconomicIndicator;

use InvalidArgumentException;

final class EconomicIndicatorCatalog
{
    /** @return list<array{code: string, kind: string, name: string, sourceKey: string, sourceSeriesCode: string, periodicity: string, unit: string, accumulationMode: string, initialReferenceDate: string}> */
    public static function all(): array
    {
        $definitions = [
            ['code' => 'SELIC_DAILY', 'kind' => 'INDEX', 'name' => 'Taxa Selic', 'sourceKey' => 'BCB_SGS', 'sourceSeriesCode' => '11', 'periodicity' => 'DAILY', 'unit' => 'PERCENT_DAY', 'accumulationMode' => 'COMPOUND_PUBLISHED_RATE', 'initialReferenceDate' => '1986-06-04'],
            ['code' => 'IPCA_MONTHLY', 'kind' => 'INDEX', 'name' => 'IPCA', 'sourceKey' => 'BCB_SGS', 'sourceSeriesCode' => '433', 'periodicity' => 'MONTHLY', 'unit' => 'PERCENT_MONTH', 'accumulationMode' => 'COMPOUND_PUBLISHED_RATE', 'initialReferenceDate' => '1980-02-01'],
            ['code' => 'IGPM_MONTHLY', 'kind' => 'INDEX', 'name' => 'IGP-M', 'sourceKey' => 'BCB_SGS', 'sourceSeriesCode' => '189', 'periodicity' => 'MONTHLY', 'unit' => 'PERCENT_MONTH', 'accumulationMode' => 'COMPOUND_PUBLISHED_RATE', 'initialReferenceDate' => '1989-06-01'],
            ['code' => 'INCC_MONTHLY', 'kind' => 'INDEX', 'name' => 'INCC', 'sourceKey' => 'BCB_SGS', 'sourceSeriesCode' => '192', 'periodicity' => 'MONTHLY', 'unit' => 'PERCENT_MONTH', 'accumulationMode' => 'COMPOUND_PUBLISHED_RATE', 'initialReferenceDate' => '1989-06-01'],
            ['code' => 'SAVINGS_RETURN_DAILY', 'kind' => 'INDEX', 'name' => 'Remuneração da poupança', 'sourceKey' => 'BCB_SGS', 'sourceSeriesCode' => '195', 'periodicity' => 'DAILY', 'unit' => 'PERCENT_MONTH', 'accumulationMode' => 'NO_GLOBAL_ACCUMULATION', 'initialReferenceDate' => '2012-05-04'],
            ['code' => 'PTAX_USD_BRL', 'kind' => 'PTAX', 'name' => 'PTAX USD/BRL', 'sourceKey' => 'BCB_PTAX', 'sourceSeriesCode' => 'USD', 'periodicity' => 'DAILY', 'unit' => 'BRL_PER_UNIT', 'accumulationMode' => 'NOT_APPLICABLE', 'initialReferenceDate' => '1984-11-28'],
            ['code' => 'PTAX_EUR_BRL', 'kind' => 'PTAX', 'name' => 'PTAX EUR/BRL', 'sourceKey' => 'BCB_PTAX', 'sourceSeriesCode' => 'EUR', 'periodicity' => 'DAILY', 'unit' => 'BRL_PER_UNIT', 'accumulationMode' => 'NOT_APPLICABLE', 'initialReferenceDate' => '2002-01-02'],
        ];

        /** @var list<string> $enabledSeries */
        $enabledSeries = config('economic-indicators.enabled_series', []);
        if ($enabledSeries === []) {
            return $definitions;
        }

        $availableCodes = array_column($definitions, 'code');
        $unknownCodes = array_values(array_diff($enabledSeries, $availableCodes));
        if ($unknownCodes !== []) {
            throw new InvalidArgumentException('ECONOMIC_INDICATOR_ENABLED_SERIES contains unknown series: '.implode(', ', $unknownCodes));
        }

        return array_values(array_filter(
            $definitions,
            static fn (array $definition): bool => in_array($definition['code'], $enabledSeries, true),
        ));
    }
}
