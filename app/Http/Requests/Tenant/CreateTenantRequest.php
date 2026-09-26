<?php

namespace App\Http\Requests\Tenant;

class CreateTenantRequest extends TenantRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return array_merge($this->all(), ['idempotencyKey' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'displayName' => ['required', 'string', 'max:120', 'not_regex:/^\s*$/u'],
            'idempotencyKey' => ['required', 'string', 'uuid'],
        ];
    }
}
