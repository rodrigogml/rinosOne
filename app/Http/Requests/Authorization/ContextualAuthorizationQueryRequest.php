<?php

namespace App\Http\Requests\Authorization;

class ContextualAuthorizationQueryRequest extends AdministrativeAuthorizationRequest
{
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:160'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
            'operation' => ['nullable', 'string', 'max:80'],
            'targetType' => ['nullable', 'string', 'max:80'],
            'targetId' => ['nullable', 'integer', 'min:1'],
            'occurredAfter' => ['nullable', 'date'],
            'occurredBefore' => ['nullable', 'date'],
        ];
    }
}
