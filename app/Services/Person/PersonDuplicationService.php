<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Person\PersonAuditAction;
use App\Domain\Person\PersonDuplicationOptions;
use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\PersonBankAccount;
use App\Models\PersonContact;
use App\Models\PersonPixKey;
use Illuminate\Database\ConnectionInterface;

class PersonDuplicationService
{
    public function __construct(private readonly PersonAuditLogger $audit) {}

    public function duplicate(ConnectionInterface $connection, int $personId, PersonDuplicationOptions $options, ?int $expectedVersion = null, ?int $actorUserId = null, ?string $correlationId = null): Person
    {
        return $connection->transaction(function () use ($connection, $personId, $options, $expectedVersion, $actorUserId, $correlationId): Person {
            $source = (new Person)->forTenantConnection($connection)->newQuery()->find($personId);
            if ($source === null) {
                throw new PersonValidationException(['person' => 'not_found']);
            }
            if ($expectedVersion !== null && $source->version !== $expectedVersion) {
                throw new PersonVersionConflictException;
            }
            $copy = (new Person)->forTenantConnection($connection);
            $copy->fill($source->only(['personType', 'name', 'alias', 'displayName', 'rg', 'rgIssuer', 'pisNis', 'passportNumber', 'foreignDocumentNumber', 'birthDate', 'foundationDate', 'notes', 'status']));
            $copy->cpf = null;
            $copy->cnpj = null;
            $copy->version = 1;
            $copy->save();
            if ($options->copyAddresses) {
                foreach ((new PersonAddress)->forTenantConnection($connection)->newQuery()->where('idPerson', $source->id)->get() as $address) {
                    $attributes = $address->only(['label', 'addressType', 'idCountry', 'idBrazilState', 'idBrazilMunicipality', 'idLocalityReference', 'stateText', 'cityText', 'street', 'number', 'complement', 'district', 'reference', 'postalCode']);
                    (new PersonAddress)->forTenantConnection($connection)->fill([...$attributes, 'idPerson' => $copy->id])->save();
                }
            }
            if ($options->copyContacts) {
                foreach ((new PersonContact)->forTenantConnection($connection)->newQuery()->where('idPerson', $source->id)->get() as $contact) {
                    (new PersonContact)->forTenantConnection($connection)->fill([...$contact->only(['contactType', 'value', 'normalizedValue', 'description']), 'idPerson' => $copy->id])->save();
                }
            }
            if ($options->copyBankAccounts) {
                foreach ((new PersonBankAccount)->forTenantConnection($connection)->newQuery()->where('idPerson', $source->id)->get() as $account) {
                    (new PersonBankAccount)->forTenantConnection($connection)->fill([...$account->only(['label', 'idFinancialInstitution', 'accountType', 'agency', 'agencyDigit', 'accountNumber', 'accountDigit', 'status']), 'idPerson' => $copy->id])->save();
                }
            }
            if ($options->copyPixKeys) {
                foreach ((new PersonPixKey)->forTenantConnection($connection)->newQuery()->where('idPerson', $source->id)->get() as $key) {
                    (new PersonPixKey)->forTenantConnection($connection)->fill([...$key->only(['keyType', 'keyValue', 'normalizedKeyValue', 'status']), 'idPerson' => $copy->id])->save();
                }
            }

            $this->audit->record($connection, $copy->id, PersonAuditAction::CREATED, $actorUserId, $correlationId);

            return $copy;
        });
    }
}
