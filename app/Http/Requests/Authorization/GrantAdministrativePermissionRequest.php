<?php

namespace App\Http\Requests\Authorization;

class GrantAdministrativePermissionRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permissionId' => ['required', 'integer', 'min:1']];
    }
}
