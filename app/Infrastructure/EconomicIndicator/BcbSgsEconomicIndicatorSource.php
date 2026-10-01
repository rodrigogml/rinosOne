<?php

namespace App\Infrastructure\EconomicIndicator;

use App\Models\EconomicIndicatorSeries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use UnexpectedValueException;

final class BcbSgsEconomicIndicatorSource implements EconomicIndicatorSource
{
    public function __construct(private readonly HttpFactory $http, private readonly BcbHttpRequestFactory $requestFactory) {}

    public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
    {
        try {
            $response = $this->requestFactory->create($this->http)->get(rtrim((string) config('economic-indicators.bcb.sgs_base_url'), '/').'/bcdata.sgs.'.$series->sourceSeriesCode.'/dados', [
                'formato' => 'json', 'dataInicial' => $from->format('d/m/Y'), 'dataFinal' => $to->format('d/m/Y'),
            ]);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return [];
            }

            throw $exception;
        }
        if ($response->status() === 404) {
            return [];
        }

        $response = $response->throw()->json();
        if (! is_array($response)) {
            throw new UnexpectedValueException('BCB SGS response is invalid.');
        }
        if (isset($response['erro'])) {
            if (($response['erro']['statusCode'] ?? null) === 404) {
                return [];
            }

            throw new UnexpectedValueException('BCB SGS response contains an error.');
        }
        $records = [];
        foreach ($response as $item) {
            if (! is_array($item) || ! isset($item['data'], $item['valor'])) {
                throw new UnexpectedValueException('BCB SGS item is invalid.');
            }
            $date = CarbonImmutable::createFromFormat('d/m/Y', (string) $item['data'])->startOfDay();
            $value = str_replace(',', '.', (string) $item['valor']);
            if (! is_numeric($value)) {
                throw new UnexpectedValueException('BCB SGS value is invalid.');
            }
            $records[] = new EconomicIndicatorSourceRecord($date, $value, 'BCB_SGS:'.$series->sourceSeriesCode.':'.$date->toDateString().':'.$value);
        }

        return $records;
    }
}
