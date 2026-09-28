<?php

namespace App\Http\Requests\Authorization;

class CreateAuthorizationDelegationRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['delegatorUserId' => ['required', 'integer', 'min:1'], 'recipientUserId' => ['required', 'integer', 'min:1', 'different:delegatorUserId'], 'permissionId' => ['required', 'integer', 'min:1'], 'originAssignmentId' => ['required', 'integer', 'min:1'], 'startsAt' => ['required', 'date'], 'endsAt' => ['required', 'date', 'after:startsAt'], 'limits' => ['nullable', 'array']];
    }
}
