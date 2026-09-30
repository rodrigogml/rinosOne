<?php

namespace App\Http\Requests\Person;

use App\Domain\Person\PersonIdentityInput;
use App\Domain\Person\PersonType;
use App\Http\Dto\Person\PersonAggregateData;

abstract class PersonIdentityRequest extends PersonApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    protected function identityRules(): array
    {
        return [
            'personType' => ['required', 'string', 'in:PF,PJ'],
            'name' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/^\s*$/u'],
            'alias' => ['nullable', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:32'],
            'cnpj' => ['nullable', 'string', 'max:32'],
            'rg' => ['nullable', 'string', 'max:40'],
            'rgIssuer' => ['nullable', 'string', 'max:60'],
            'pisNis' => ['nullable', 'string', 'max:20'],
            'passportNumber' => ['nullable', 'string', 'max:40'],
            'foreignDocumentNumber' => ['nullable', 'string', 'max:60'],
            'birthDate' => ['nullable', 'date'],
            'foundationDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:16777215'],
        ];
    }

    /** @return array<string, list<string>> */
    protected function collectionRules(): array
    {
        return [
            'addresses' => ['sometimes', 'array'],
            'addresses.*.label' => ['required_with:addresses', 'string', 'max:60'],
            'addresses.*.addressType' => ['required_with:addresses', 'string', 'in:RESIDENTIAL,COMMERCIAL,BILLING,DELIVERY,BRANCH,OTHER'],
            'addresses.*.idCountry' => ['required_with:addresses', 'integer', 'min:1'],
            'addresses.*.idBrazilState' => ['nullable', 'integer', 'min:1'],
            'addresses.*.idBrazilMunicipality' => ['nullable', 'integer', 'min:1'],
            'addresses.*.idLocalityReference' => ['nullable', 'integer', 'min:1'],
            'addresses.*.stateText' => ['nullable', 'string', 'max:120'],
            'addresses.*.cityText' => ['nullable', 'string', 'max:120'],
            'addresses.*.street' => ['nullable', 'string', 'max:255'],
            'addresses.*.number' => ['nullable', 'string', 'max:40'],
            'addresses.*.complement' => ['nullable', 'string', 'max:255'],
            'addresses.*.district' => ['nullable', 'string', 'max:255'],
            'addresses.*.reference' => ['nullable', 'string', 'max:255'],
            'addresses.*.postalCode' => ['nullable', 'string', 'max:24'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*.contactType' => ['required_with:contacts', 'string', 'in:EMAIL,PHONE,MOBILE,WHATSAPP,WEBSITE,OTHER'],
            'contacts.*.value' => ['required_with:contacts', 'string', 'max:512'],
            'contacts.*.description' => ['nullable', 'string', 'max:255'],
            'bankAccounts' => ['sometimes', 'array'],
            'bankAccounts.*.label' => ['required_with:bankAccounts', 'string', 'max:60'],
            'bankAccounts.*.idFinancialInstitution' => ['nullable', 'integer', 'min:1'],
            'bankAccounts.*.accountType' => ['required_with:bankAccounts', 'string', 'in:CHECKING,SAVINGS,INVESTMENT,SALARY,OTHER'],
            'bankAccounts.*.agency' => ['nullable', 'string', 'max:32'],
            'bankAccounts.*.agencyDigit' => ['nullable', 'string', 'max:32'],
            'bankAccounts.*.accountNumber' => ['nullable', 'string', 'max:64'],
            'bankAccounts.*.accountDigit' => ['nullable', 'string', 'max:32'],
            'bankAccounts.*.status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE'],
            'pixKeys' => ['sometimes', 'array'],
            'pixKeys.*.keyType' => ['required_with:pixKeys', 'string', 'in:CPF,CNPJ,EMAIL,PHONE,RANDOM'],
            'pixKeys.*.value' => ['required_with:pixKeys', 'string', 'max:255'],
            'pixKeys.*.status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE'],
            'relationships' => ['sometimes', 'array'],
            'relationships.*.idTargetPerson' => ['required_with:relationships', 'integer', 'min:1'],
            'relationships.*.relationshipType' => ['required_with:relationships', 'string', 'in:CHILD_OF,PARENT_OF,GRANDCHILD_OF,GRANDPARENT_OF,SPOUSE_OF,PARTNER_OF,EMPLOYEE_OF,EMPLOYER_OF,CONTRACTOR_OF,CONTRACTING_PARTY_OF,OTHER'],
            'relationships.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function aggregateData(): PersonAggregateData
    {
        $validated = $this->validated();

        return new PersonAggregateData(
            new PersonIdentityInput(
                PersonType::from($validated['personType']),
                $validated['name'],
                $validated['alias'] ?? null,
                $validated['cpf'] ?? null,
                $validated['cnpj'] ?? null,
                $validated['rg'] ?? null,
                $validated['rgIssuer'] ?? null,
                $validated['pisNis'] ?? null,
                $validated['passportNumber'] ?? null,
                $validated['foreignDocumentNumber'] ?? null,
                $validated['birthDate'] ?? null,
                $validated['foundationDate'] ?? null,
                $validated['notes'] ?? null,
            ),
            $validated['addresses'] ?? null,
            $validated['contacts'] ?? null,
            $validated['bankAccounts'] ?? null,
            $validated['pixKeys'] ?? null,
            $validated['relationships'] ?? null,
        );
    }
}
