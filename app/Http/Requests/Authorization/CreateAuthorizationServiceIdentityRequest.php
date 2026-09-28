<?php

namespace App\Http\Requests\Authorization;

class CreateAuthorizationServiceIdentityRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['ownerUserId' => ['required', 'integer', 'min:1'], 'displayName' => ['required', 'string', 'max:160'], 'purpose' => ['required', 'string', 'max:500'], 'startsAt' => ['nullable', 'date'], 'endsAt' => ['nullable', 'date', 'after:startsAt']];
    }
}
