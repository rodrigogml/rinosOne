<?php

namespace App\Http\Requests\Authorization;

class UpdateResourceShareRequest extends AdministrativeAuthorizationRequest
{
    public function rules(): array
    {
        return [
            'relation' => ['required', 'string', 'in:READ,EDIT'],
            'expectedContextVersion' => ['nullable', 'string', 'max:64'],
        ];
    }
}
