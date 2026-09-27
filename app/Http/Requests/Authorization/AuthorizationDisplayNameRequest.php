<?php

namespace App\Http\Requests\Authorization;

class AuthorizationDisplayNameRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['displayName' => ['required', 'string', 'max:160', 'not_regex:/^\s*$/u']];
    }
}
