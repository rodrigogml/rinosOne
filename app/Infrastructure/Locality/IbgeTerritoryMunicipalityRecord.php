<?php

namespace App\Infrastructure\Locality;

use UnexpectedValueException;

final readonly class IbgeTerritoryMunicipalityRecord
{
    public function __construct(
        public string $ibgeCode,
        public string $stateIbgeCode,
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromIbge(array $payload): self
    {
        $statePayload = $payload['microrregiao']['mesorregiao']['UF']
            ?? $payload['regiao-imediata']['regiao-intermediaria']['UF']
            ?? null;

        if (! is_array($statePayload)) {
            throw new UnexpectedValueException('IBGE municipality response is missing its state identity.');
        }

        return new self(
            ibgeCode: self::required($payload, 'id'),
            stateIbgeCode: self::required($statePayload, 'id'),
            name: self::required($payload, 'nome'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function required(array $payload, string $field): string
    {
        $value = trim((string) ($payload[$field] ?? ''));

        if ($value === '') {
            throw new UnexpectedValueException("IBGE municipality response is missing required field {$field}.");
        }

        return $value;
    }
}
