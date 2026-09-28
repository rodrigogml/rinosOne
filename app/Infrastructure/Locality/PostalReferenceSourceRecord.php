<?php

namespace App\Infrastructure\Locality;

final readonly class PostalReferenceSourceRecord
{
    /**
     * @param  array<string, string|null>  $observedValues
     */
    public function __construct(
        public string $sourceKey,
        public ?string $externalIdentifier,
        public string $identitySignature,
        public string $countryCode,
        public string $normalizedPostalCode,
        public ?string $streetName,
        public ?string $neighborhoodName,
        public ?string $municipalityName,
        public ?string $stateAbbreviation,
        public ?string $municipalityIbgeCode,
        public array $observedValues,
    ) {}
}
