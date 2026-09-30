<?php

namespace App\Http\Resources\Person;

use App\Domain\Person\PersonRelationshipType;
use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\PersonBankAccount;
use App\Models\PersonContact;
use App\Models\PersonPixKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Person */
class PersonDetailResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  list<array{relationshipId: int, direction: string, relationshipType: PersonRelationshipType, otherPersonId: int, description: ?string}>  $relationships
     */
    public function __construct(Person $resource, private readonly array $relationships = [])
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Person $person */
        $person = $this->resource;

        return [
            'id' => $person->id,
            'personType' => $person->personType->value,
            'name' => $person->name,
            'alias' => $person->alias,
            'displayName' => $person->displayName,
            'contactCount' => (int) ($person->contacts_count ?? ($person->relationLoaded('contacts') ? $person->contacts->count() : 0)),
            'cpf' => $person->cpf,
            'cnpj' => $person->cnpj,
            'rg' => $person->rg,
            'rgIssuer' => $person->rgIssuer,
            'pisNis' => $person->pisNis,
            'passportNumber' => $person->passportNumber,
            'foreignDocumentNumber' => $person->foreignDocumentNumber,
            'birthDate' => $person->birthDate?->toDateString(),
            'foundationDate' => $person->foundationDate?->toDateString(),
            'notes' => $person->notes,
            'status' => $person->status->value,
            'version' => $person->version,
            'addresses' => $this->whenLoaded('addresses', fn () => $person->addresses->map(fn (PersonAddress $address): array => [
                'id' => $address->id,
                'label' => $address->label,
                'addressType' => $address->addressType->value,
                'idCountry' => $address->idCountry,
                'idBrazilState' => $address->idBrazilState,
                'idBrazilMunicipality' => $address->idBrazilMunicipality,
                'idLocalityReference' => $address->idLocalityReference,
                'stateText' => $address->stateText,
                'cityText' => $address->cityText,
                'street' => $address->street,
                'number' => $address->number,
                'complement' => $address->complement,
                'district' => $address->district,
                'reference' => $address->reference,
                'postalCode' => $address->postalCode,
            ])->values()),
            'contacts' => $this->whenLoaded('contacts', fn () => $person->contacts->map(fn (PersonContact $contact): array => [
                'id' => $contact->id,
                'contactType' => $contact->contactType->value,
                'value' => $contact->value,
                'description' => $contact->description,
            ])->values()),
            'bankAccounts' => $this->whenLoaded('bankAccounts', fn () => $person->bankAccounts->map(fn (PersonBankAccount $account): array => [
                'id' => $account->id,
                'label' => $account->label,
                'idFinancialInstitution' => $account->idFinancialInstitution,
                'accountType' => $account->accountType->value,
                'agency' => $account->agency,
                'agencyDigit' => $account->agencyDigit,
                'accountNumber' => $account->accountNumber,
                'accountDigit' => $account->accountDigit,
                'status' => $account->status->value,
            ])->values()),
            'pixKeys' => $this->whenLoaded('pixKeys', fn () => $person->pixKeys->map(fn (PersonPixKey $key): array => [
                'id' => $key->id,
                'keyType' => $key->keyType->value,
                'value' => $key->keyValue,
                'status' => $key->status->value,
            ])->values()),
            'relationships' => array_map(fn (array $relationship): array => [
                'id' => $relationship['relationshipId'],
                'direction' => $relationship['direction'],
                'relationshipType' => $relationship['relationshipType']->value,
                'displayRelationshipType' => $this->displayRelationshipType($relationship['relationshipType']->value),
                'idOtherPerson' => $relationship['otherPersonId'],
                'description' => $relationship['description'],
            ], $this->relationships),
        ];
    }

    private function displayRelationshipType(string $type): string
    {
        return match ($type) {
            'CHILD_OF' => 'Filho(a) de',
            'PARENT_OF' => 'Pai/mãe de',
            'GRANDCHILD_OF' => 'Neto(a) de',
            'GRANDPARENT_OF' => 'Avô/avó de',
            'SPOUSE_OF' => 'Cônjuge de',
            'PARTNER_OF' => 'Parceiro(a) de',
            'EMPLOYEE_OF' => 'Funcionário(a) de',
            'EMPLOYER_OF' => 'Empregador(a) de',
            'CONTRACTOR_OF' => 'Contratado(a) por',
            'CONTRACTING_PARTY_OF' => 'Contratante de',
            default => 'Outro relacionamento com',
        };
    }
}
