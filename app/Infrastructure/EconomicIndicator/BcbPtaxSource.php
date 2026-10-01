<?php

namespace App\Infrastructure\EconomicIndicator;

use App\Models\EconomicIndicatorSeries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use UnexpectedValueException;

final class BcbPtaxSource implements EconomicPtaxSource
{
    public function __construct(private readonly HttpFactory $http, private readonly BcbHttpRequestFactory $requestFactory) {}

    public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
    {
        $base = rtrim((string) config('economic-indicators.bcb.ptax_base_url'), '/');
        $response = $this->requestFactory->create($this->http)->get($base.'/CotacaoMoedaPeriodo(moeda=@moeda,dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)', [
            '@moeda' => "'{$series->sourceSeriesCode}'", '@dataInicial' => "'{$from->format('m-d-Y')}'", '@dataFinalCotacao' => "'{$to->format('m-d-Y')}'", '$format' => 'json',
        ])->throw()->json();
        if (! is_array($response) || ! is_array($response['value'] ?? null)) {
            throw new UnexpectedValueException('BCB PTAX response is invalid.');
        }
        $records = [];
        foreach ($response['value'] as $item) {
            if (! is_array($item) || ($item['tipoBoletim'] ?? null) !== 'Fechamento') {
                continue;
            }
            if (! isset($item['dataHoraCotacao'], $item['cotacaoCompra'], $item['cotacaoVenda'])) {
                throw new UnexpectedValueException('BCB PTAX item is invalid.');
            }
            $quotedAt = CarbonImmutable::parse((string) $item['dataHoraCotacao']);
            $buy = (string) $item['cotacaoCompra'];
            $sell = (string) $item['cotacaoVenda'];
            if (! is_numeric($buy) || ! is_numeric($sell)) {
                throw new UnexpectedValueException('BCB PTAX value is invalid.');
            }
            $mid = bcdiv(bcadd($buy, $sell, 12), '2', 12);
            $records[] = new EconomicPtaxSourceRecord($quotedAt->startOfDay(), $quotedAt, $buy, $sell, $mid, 'BCB_PTAX:'.$series->sourceSeriesCode.':'.$quotedAt->toIso8601String().':'.$buy.':'.$sell);
        }

        return $records;
    }
}
