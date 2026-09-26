<?php

namespace App\Infrastructure\FinancialInstitution;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use UnexpectedValueException;

final class BcbFinancialInstitutionSource implements FinancialInstitutionSource
{
    public function __construct(private readonly HttpFactory $http) {}

    public function fetch(CarbonInterface $referenceDate): array
    {
        $url = $this->collectionUrl($referenceDate);
        $records = [];

        do {
            $response = $this->http
                ->acceptJson()
                ->timeout(config('financial-institutions.bcb.timeout_seconds'))
                ->retry(2, 250)
                ->get($url)
                ->throw();

            $body = $response->json();

            if (! is_array($body) || ! is_array($body['value'] ?? null)) {
                throw new UnexpectedValueException('BCB response does not contain an OData value collection.');
            }

            foreach ($body['value'] as $item) {
                if (! is_array($item)) {
                    throw new UnexpectedValueException('BCB response contains an invalid OData item.');
                }

                $records[] = FinancialInstitutionSourceRecord::fromBcb($item, $referenceDate);
            }

            $nextLink = $body['@odata.nextLink'] ?? null;
            $url = is_string($nextLink) && $nextLink !== '' ? $nextLink : null;
        } while ($url !== null);

        return $records;
    }

    private function collectionUrl(CarbonInterface $referenceDate): string
    {
        $baseUrl = rtrim((string) config('financial-institutions.bcb.base_url'), '/');

        return $baseUrl.'/EntidadesSupervisionadas(dataBase=\''.$referenceDate->format('m-d-Y').'\')?$format=json';
    }
}
