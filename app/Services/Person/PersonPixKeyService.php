<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonPixKeyInput;
use App\Domain\Person\PersonPixKeyValidator;
use App\Models\Person;
use App\Models\PersonPixKey;
use Illuminate\Database\ConnectionInterface;

class PersonPixKeyService
{
    public function __construct(private readonly PersonPixKeyValidator $validator) {}

    public function create(ConnectionInterface $connection, int $personId, PersonPixKeyInput $input): PersonPixKey
    {
        if ((new Person)->forTenantConnection($connection)->newQuery()->whereKey($personId)->doesntExist()) {
            throw new PersonValidationException(['person' => 'not_found']);
        }
        $attributes = $this->validator->validate($input);
        $model = (new PersonPixKey)->forTenantConnection($connection);
        if ($model->newQuery()->where('idPerson', $personId)->where('keyType', $attributes['keyType'])->where('normalizedKeyValue', $attributes['normalizedKeyValue'])->exists()) {
            throw new PersonValidationException(['key' => 'duplicate']);
        }
        $key = $model;
        $key->fill([...$attributes, 'idPerson' => $personId]);
        $key->save();

        return $key;
    }
}
