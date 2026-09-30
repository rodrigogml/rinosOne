<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonBankAccountInput;
use App\Domain\Person\PersonBankAccountValidator;
use App\Models\FinancialInstitution;
use App\Models\Person;
use App\Models\PersonBankAccount;
use Illuminate\Database\ConnectionInterface;

class PersonBankAccountService
{
    public function __construct(private readonly PersonBankAccountValidator $validator) {}

    public function create(ConnectionInterface $connection, int $personId, PersonBankAccountInput $input): PersonBankAccount
    {
        if ((new Person)->forTenantConnection($connection)->newQuery()->whereKey($personId)->doesntExist()) {
            throw new PersonValidationException(['person' => 'not_found']);
        }
        if ($input->financialInstitutionId !== null && ! FinancialInstitution::query()->whereKey($input->financialInstitutionId)->where('activeForSelection', true)->exists()) {
            throw new PersonValidationException(['idFinancialInstitution' => 'not_found']);
        }
        $account = (new PersonBankAccount)->forTenantConnection($connection);
        $account->fill([...$this->validator->validate($input), 'idPerson' => $personId]);
        $account->save();

        return $account;
    }
}
