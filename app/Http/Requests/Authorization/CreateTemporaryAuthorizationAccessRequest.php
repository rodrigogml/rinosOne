<?php

namespace App\Http\Requests\Authorization;

class CreateTemporaryAuthorizationAccessRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permissionId' => ['required', 'integer', 'min:1'], 'startsAt' => ['required', 'date'], 'endsAt' => ['required', 'date', 'after:startsAt']];
    }
}
