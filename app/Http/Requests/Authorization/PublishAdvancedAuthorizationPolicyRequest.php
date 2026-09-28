<?php

namespace App\Http\Requests\Authorization;

class PublishAdvancedAuthorizationPolicyRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['key' => ['required', 'string', 'max:160'], 'definition' => ['required', 'array']];
    }
}
