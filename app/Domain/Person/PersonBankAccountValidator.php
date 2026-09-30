<?php

namespace App\Domain\Person;

use App\Domain\Person\Exception\PersonValidationException;

class PersonBankAccountValidator
{
    /** @return array<string, int|string|PersonBankAccountType|PersonStatus|null> */
    public function validate(PersonBankAccountInput $input): array
    {
        if (trim($input->label) === '' || mb_strlen(trim($input->label), 'UTF-8') > 60 || ($input->financialInstitutionId !== null && $input->financialInstitutionId < 1)) {
            throw new PersonValidationException(['account' => 'invalid']);
        }

        return ['label' => trim($input->label), 'accountType' => $input->accountType, 'idFinancialInstitution' => $input->financialInstitutionId, 'agency' => $this->text($input->agency), 'agencyDigit' => $this->text($input->agencyDigit), 'accountNumber' => $this->text($input->accountNumber), 'accountDigit' => $this->text($input->accountDigit), 'status' => $input->status];
    }

    private function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
