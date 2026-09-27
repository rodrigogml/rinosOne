<?php

namespace App\Http\Requests\Authorization;

class AuthorizationRoleIdRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['roleId' => ['required', 'integer', 'min:1']];
    }
}
