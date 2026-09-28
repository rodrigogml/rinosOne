<?php

namespace App\Infrastructure\Locality;

use UnexpectedValueException;

final readonly class IbgeTerritoryStateRecord
{
    public function __construct(
        public string $ibgeCode,
        public string $abbreviation,
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromIbge(array $payload): self
    {
        return new self(
            ibgeCode: self::required($payload, 'id'),
            abbreviation: self::required($payload, 'sigla'),
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
            throw new UnexpectedValueException("IBGE state response is missing required field {$field}.");
        }

        return $value;
    }
}
