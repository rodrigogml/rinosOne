<?php

namespace App\Http\Requests\Authorization;

class ResourceShareRequest extends AdministrativeAuthorizationRequest
{
    public function rules(): array
    {
        return [
            'subjectId' => ['required', 'integer', 'min:1'],
            'relation' => ['required', 'string', 'in:READ,EDIT'],
            'expectedContextVersion' => ['nullable', 'string', 'max:64'],
        ];
    }
}
