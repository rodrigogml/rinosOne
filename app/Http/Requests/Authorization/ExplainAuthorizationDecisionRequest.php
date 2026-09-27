<?php

namespace App\Http\Requests\Authorization;

class ExplainAuthorizationDecisionRequest extends AdministrativeAuthorizationRequest
{
    public function rules(): array
    {
        return ['permissionKey' => ['required', 'string', 'max:160', 'regex:/^tenant\.[a-z0-9.-]+$/']];
    }
}
