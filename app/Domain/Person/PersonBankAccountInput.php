<?php

namespace App\Domain\Person;

readonly class PersonBankAccountInput
{
    public function __construct(public string $label, public PersonBankAccountType $accountType, public ?int $financialInstitutionId = null, public ?string $agency = null, public ?string $agencyDigit = null, public ?string $accountNumber = null, public ?string $accountDigit = null, public PersonStatus $status = PersonStatus::ACTIVE) {}
}
