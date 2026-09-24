<?php

namespace App\Http\Requests\Tenant;

class ChangeTenantAvailabilityRequest extends TenantRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['state' => ['required', 'string', 'in:ACTIVE,INACTIVE']];
    }
}
