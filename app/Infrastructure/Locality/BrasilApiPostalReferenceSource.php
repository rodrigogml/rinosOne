<?php

namespace App\Infrastructure\Locality;

use Illuminate\Http\Client\Response;
use UnexpectedValueException;

final class BrasilApiPostalReferenceSource extends AbstractPostalReferenceHttpSource
{
    public function sourceKey(): string
    {
        return 'BRASIL_API';
    }

    public function recordsFromResponse(Response $response, string $countryCode, string $normalizedPostalCode): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException('BRASIL_API response is not an object.');
        }

        $ibge = $payload['ibge'] ?? [];
        if (! is_array($ibge)) {
            throw new UnexpectedValueException('BRASIL_API response contains an invalid IBGE object.');
        }

        return [$this->record(
            payload: $payload,
            countryCode: $countryCode,
            normalizedPostalCode: $normalizedPostalCode,
            streetName: $this->optionalString($payload, 'street'),
            neighborhoodName: $this->optionalString($payload, 'neighborhood'),
            municipalityName: $this->requiredString($payload, 'city'),
            stateAbbreviation: $this->requiredString($payload, 'state'),
            municipalityIbgeCode: $this->optionalString($ibge, 'city'),
        )];
    }

    protected function configurationKey(): string
    {
        return 'localities.postal.brasil_api';
    }

    protected function endpoint(string $normalizedPostalCode): string
    {
        return rtrim((string) config($this->configurationKey().'.base_url'), '/').'/'.$normalizedPostalCode;
    }
}
