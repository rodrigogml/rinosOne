<?php

namespace App\Http\Requests\Person;

class UpdatePersonRequest extends PersonIdentityRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...$this->identityRules(), ...$this->collectionRules(), 'version' => ['required', 'integer', 'min:1']];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('version');
    }
}
