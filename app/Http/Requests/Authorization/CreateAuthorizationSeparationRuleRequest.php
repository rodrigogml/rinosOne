<?php

namespace App\Http\Requests\Authorization;

class CreateAuthorizationSeparationRuleRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permissionId' => ['required', 'integer', 'min:1'], 'incompatiblePermissionId' => ['required', 'integer', 'min:1', 'different:permissionId']];
    }
}
