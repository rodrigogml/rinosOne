<?php

namespace App\Http\Requests\Authorization;

class AuthorizationAuditIndexRequest extends AdministrativeAuthorizationRequest
{
    public function rules(): array
    {
        return [
            'operation' => ['nullable', 'string', 'max:160'],
            'targetType' => ['nullable', 'string', 'max:160'],
            'targetId' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
