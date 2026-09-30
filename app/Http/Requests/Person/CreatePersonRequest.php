<?php

namespace App\Http\Requests\Person;

class CreatePersonRequest extends PersonIdentityRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...$this->identityRules(), ...$this->collectionRules()];
    }
}
