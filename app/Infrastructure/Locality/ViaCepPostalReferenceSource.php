<?php

namespace App\Infrastructure\Locality;

use Illuminate\Http\Client\Response;
use UnexpectedValueException;

final class ViaCepPostalReferenceSource extends AbstractPostalReferenceHttpSource
{
    public function sourceKey(): string
    {
        return 'VIA_CEP';
    }

    public function recordsFromResponse(Response $response, string $countryCode, string $normalizedPostalCode): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException('VIA_CEP response is not an object.');
        }

        if (filter_var($payload['erro'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        return [$this->record(
            payload: $payload,
            countryCode: $countryCode,
            normalizedPostalCode: $normalizedPostalCode,
            streetName: $this->optionalString($payload, 'logradouro'),
            neighborhoodName: $this->optionalString($payload, 'bairro'),
            municipalityName: $this->requiredString($payload, 'localidade'),
            stateAbbreviation: $this->requiredString($payload, 'uf'),
            municipalityIbgeCode: $this->optionalString($payload, 'ibge'),
        )];
    }

    protected function configurationKey(): string
    {
        return 'localities.postal.via_cep';
    }

    protected function endpoint(string $normalizedPostalCode): string
    {
        return rtrim((string) config($this->configurationKey().'.base_url'), '/').'/'.$normalizedPostalCode.'/json/';
    }
}
