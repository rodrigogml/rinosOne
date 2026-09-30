<?php

namespace App\Http\Requests\Authorization;

class ContextualExplainAuthorizationDecisionRequest extends AdministrativeAuthorizationRequest
{
    public function rules(): array
    {
        return ['permissionKey' => ['required', 'string', 'max:160', 'regex:/^[a-z]+\.[a-z0-9.-]+$/']];
    }
}
