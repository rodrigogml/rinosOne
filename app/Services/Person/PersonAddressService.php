<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonAddressInput;
use App\Domain\Person\PersonAddressValidator;
use App\Models\Person;
use App\Models\PersonAddress;
use Illuminate\Database\ConnectionInterface;

class PersonAddressService
{
    public function __construct(private readonly PersonAddressValidator $validator, private readonly PersonAddressCoreReferenceResolver $references) {}

    public function create(ConnectionInterface $connection, int $personId, PersonAddressInput $input): PersonAddress
    {
        if ((new Person)->forTenantConnection($connection)->newQuery()->whereKey($personId)->doesntExist()) {
            throw new PersonValidationException(['person' => 'not_found']);
        }
        $this->references->assertCompatible($input);
        $address = (new PersonAddress)->forTenantConnection($connection);
        $address->fill([...$this->validator->validate($input), 'idPerson' => $personId]);
        $address->save();

        return $address;
    }
}
