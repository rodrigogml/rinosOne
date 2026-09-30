<?php

namespace App\Domain\Person;

readonly class PersonAddressInput
{
    public function __construct(
        public string $label,
        public PersonAddressType $addressType,
        public int $countryId,
        public bool $isBrazil,
        public ?int $brazilStateId,
        public ?int $brazilMunicipalityId,
        public ?int $localityReferenceId,
        public ?string $street,
        public ?string $number = null,
        public ?string $postalCode = null,
        public ?string $stateText = null,
        public ?string $cityText = null,
        public ?string $complement = null,
        public ?string $district = null,
        public ?string $reference = null,
    ) {}
}
