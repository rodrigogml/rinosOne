<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonContactInput;
use App\Domain\Person\PersonContactValidator;
use App\Models\Person;
use App\Models\PersonContact;
use Illuminate\Database\ConnectionInterface;

class PersonContactService
{
    public function __construct(private readonly PersonContactValidator $validator) {}

    public function create(ConnectionInterface $connection, int $personId, PersonContactInput $input): PersonContact
    {
        if ((new Person)->forTenantConnection($connection)->newQuery()->whereKey($personId)->doesntExist()) {
            throw new PersonValidationException(['person' => 'not_found']);
        }
        $contact = (new PersonContact)->forTenantConnection($connection);
        $contact->fill([...$this->validator->validate($input), 'idPerson' => $personId]);
        $contact->save();

        return $contact;
    }
}
