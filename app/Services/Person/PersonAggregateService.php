<?php

namespace App\Services\Person;

use App\Domain\Person\PersonAddressInput;
use App\Domain\Person\PersonAddressType;
use App\Domain\Person\PersonBankAccountInput;
use App\Domain\Person\PersonBankAccountType;
use App\Domain\Person\PersonContactInput;
use App\Domain\Person\PersonContactType;
use App\Domain\Person\PersonPixKeyInput;
use App\Domain\Person\PersonPixKeyType;
use App\Domain\Person\PersonRelationshipType;
use App\Domain\Person\PersonStatus;
use App\Http\Dto\Person\PersonAggregateData;
use App\Models\Country;
use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\PersonBankAccount;
use App\Models\PersonContact;
use App\Models\PersonPixKey;
use App\Models\PersonRelationship;
use Illuminate\Database\ConnectionInterface;

class PersonAggregateService
{
    public function __construct(
        private readonly PersonIdentityService $identity,
        private readonly PersonAddressService $addresses,
        private readonly PersonContactService $contacts,
        private readonly PersonBankAccountService $bankAccounts,
        private readonly PersonPixKeyService $pixKeys,
        private readonly PersonRelationshipService $relationships,
    ) {}

    /**
     * Creates an identity and every supplied collection in one tenant-bound
     * transaction. A collection omitted from the payload remains empty.
     */
    public function create(ConnectionInterface $connection, PersonAggregateData $data, ?int $actorUserId = null, ?string $correlationId = null): Person
    {
        return $connection->transaction(function () use ($connection, $data, $actorUserId, $correlationId): Person {
            $person = $this->identity->create($connection, $data->identity, $actorUserId, $correlationId);
            $this->createCollections($connection, $person->id, $data);

            return $person;
        });
    }

    /** Replaces only collections explicitly supplied by the caller. */
    public function update(ConnectionInterface $connection, int $personId, int $expectedVersion, PersonAggregateData $data, ?int $actorUserId = null, ?string $correlationId = null): Person
    {
        return $connection->transaction(function () use ($connection, $personId, $expectedVersion, $data, $actorUserId, $correlationId): Person {
            $person = $this->identity->update($connection, $personId, $expectedVersion, $data->identity, $actorUserId, $correlationId);
            $this->replaceCollections($connection, $personId, $data);

            return $person;
        });
    }

    private function replaceCollections(ConnectionInterface $connection, int $personId, PersonAggregateData $data): void
    {
        foreach ([
            'addresses' => PersonAddress::class,
            'contacts' => PersonContact::class,
            'bankAccounts' => PersonBankAccount::class,
            'pixKeys' => PersonPixKey::class,
        ] as $property => $model) {
            if ($data->{$property} !== null) {
                (new $model)->forTenantConnection($connection)->newQuery()->where('idPerson', $personId)->delete();
            }
        }
        if ($data->relationships !== null) {
            (new PersonRelationship)->forTenantConnection($connection)->newQuery()->where('idSourcePerson', $personId)->delete();
        }
        $this->createCollections($connection, $personId, $data);
    }

    private function createCollections(ConnectionInterface $connection, int $personId, PersonAggregateData $data): void
    {
        foreach ($data->addresses ?? [] as $address) {
            $countryCode = Country::query()->whereKey($address['idCountry'])->value('isoAlpha2');
            $this->addresses->create($connection, $personId, new PersonAddressInput(
                $address['label'],
                PersonAddressType::from($address['addressType']),
                $address['idCountry'],
                strtoupper((string) $countryCode) === 'BR',
                $address['idBrazilState'] ?? null,
                $address['idBrazilMunicipality'] ?? null,
                $address['idLocalityReference'] ?? null,
                $address['street'] ?? null,
                $address['number'] ?? null,
                $address['postalCode'] ?? null,
                $address['stateText'] ?? null,
                $address['cityText'] ?? null,
                $address['complement'] ?? null,
                $address['district'] ?? null,
                $address['reference'] ?? null,
            ));
        }
        foreach ($data->contacts ?? [] as $contact) {
            $this->contacts->create($connection, $personId, new PersonContactInput(PersonContactType::from($contact['contactType']), $contact['value'], $contact['description'] ?? null));
        }
        foreach ($data->bankAccounts ?? [] as $account) {
            $this->bankAccounts->create($connection, $personId, new PersonBankAccountInput(
                $account['label'],
                PersonBankAccountType::from($account['accountType']),
                $account['idFinancialInstitution'] ?? null,
                $account['agency'] ?? null,
                $account['agencyDigit'] ?? null,
                $account['accountNumber'] ?? null,
                $account['accountDigit'] ?? null,
                PersonStatus::from($account['status'] ?? PersonStatus::ACTIVE->value),
            ));
        }
        foreach ($data->pixKeys ?? [] as $key) {
            $this->pixKeys->create($connection, $personId, new PersonPixKeyInput(
                PersonPixKeyType::from($key['keyType']),
                $key['value'],
                PersonStatus::from($key['status'] ?? PersonStatus::ACTIVE->value),
            ));
        }
        foreach ($data->relationships ?? [] as $relationship) {
            $this->relationships->create($connection, $personId, $relationship['idTargetPerson'], PersonRelationshipType::from($relationship['relationshipType']), $relationship['description'] ?? null);
        }
    }
}
