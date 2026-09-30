<?php

namespace App\Http\Requests\Person;

use App\Domain\Person\PersonDuplicationOptions;

class DuplicatePersonRequest extends PersonApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'copyAddresses' => ['required', 'boolean'],
            'copyContacts' => ['required', 'boolean'],
            'copyBankAccounts' => ['required', 'boolean'],
            'copyPixKeys' => ['required', 'boolean'],
        ];
    }

    public function options(): PersonDuplicationOptions
    {
        return new PersonDuplicationOptions(
            copyAddresses: $this->boolean('copyAddresses'),
            copyContacts: $this->boolean('copyContacts'),
            copyBankAccounts: $this->boolean('copyBankAccounts'),
            copyPixKeys: $this->boolean('copyPixKeys'),
        );
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('version');
    }
}
