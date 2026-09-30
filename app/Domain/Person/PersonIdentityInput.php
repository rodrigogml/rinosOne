<?php

namespace App\Domain\Person;

readonly class PersonIdentityInput
{
    public function __construct(
        public PersonType $personType,
        public string $name,
        public ?string $alias = null,
        public ?string $cpf = null,
        public ?string $cnpj = null,
        public ?string $rg = null,
        public ?string $rgIssuer = null,
        public ?string $pisNis = null,
        public ?string $passportNumber = null,
        public ?string $foreignDocumentNumber = null,
        public ?string $birthDate = null,
        public ?string $foundationDate = null,
        public ?string $notes = null,
    ) {}
}
