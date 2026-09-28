<?php

namespace App\Infrastructure\Locality;

use Illuminate\Http\Client\Factory as HttpFactory;
use UnexpectedValueException;

final class IbgeTerritoryApiSource implements IbgeTerritorySource
{
    public function __construct(private readonly HttpFactory $http) {}

    public function fetch(): IbgeTerritoryCatalog
    {
        $states = $this->collection('estados');
        $municipalities = $this->collection('municipios');

        return new IbgeTerritoryCatalog(
            states: array_map($this->stateRecord(...), $states),
            municipalities: array_map($this->municipalityRecord(...), $municipalities),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collection(string $resource): array
    {
        $request = $this->http
            ->acceptJson()
            ->timeout(config('localities.ibge.timeout_seconds'))
            ->retry(2, 250);
        $caBundle = $this->caBundle();

        if (is_string($caBundle) && $caBundle !== '') {
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        $body = $request
            ->get($this->baseUrl().'/'.$resource)
            ->throw()
            ->json();

        if (! is_array($body) || ! array_is_list($body)) {
            throw new UnexpectedValueException("IBGE {$resource} response is not a collection.");
        }

        foreach ($body as $item) {
            if (! is_array($item)) {
                throw new UnexpectedValueException("IBGE {$resource} response contains an invalid item.");
            }
        }

        return $body;
    }

    /** @param array<string, mixed> $payload */
    private function stateRecord(array $payload): IbgeTerritoryStateRecord
    {
        return IbgeTerritoryStateRecord::fromIbge($payload);
    }

    /** @param array<string, mixed> $payload */
    private function municipalityRecord(array $payload): IbgeTerritoryMunicipalityRecord
    {
        return IbgeTerritoryMunicipalityRecord::fromIbge($payload);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('localities.ibge.base_url'), '/');
    }

    private function caBundle(): ?string
    {
        $configuredBundle = config('localities.ibge.ca_bundle');

        if (is_string($configuredBundle) && $configuredBundle !== '') {
            return $configuredBundle;
        }

        $localBundle = storage_path('app/certificates/cacert.pem');

        return is_file($localBundle) ? $localBundle : null;
    }
}
