<?php

namespace App\Http\Requests\Authorization;

class CreateAdministrativeRoleRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['key' => ['required', 'string', 'max:160'], 'displayName' => ['required', 'string', 'max:160', 'not_regex:/^\s*$/u'], 'description' => ['nullable', 'string', 'max:1000']];
    }
}
