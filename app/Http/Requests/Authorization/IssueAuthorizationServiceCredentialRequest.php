<?php

namespace App\Http\Requests\Authorization;

class IssueAuthorizationServiceCredentialRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['displayName' => ['required', 'string', 'max:160'], 'permissionKeys' => ['nullable', 'array'], 'permissionKeys.*' => ['string', 'max:160'], 'expiresAt' => ['nullable', 'date']];
    }
}
