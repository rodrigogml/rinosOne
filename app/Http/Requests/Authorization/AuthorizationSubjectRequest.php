<?php

namespace App\Http\Requests\Authorization;

class AuthorizationSubjectRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'userId' => ['required_without:subjectId', 'integer', 'min:1'],
            'subjectId' => ['required_without:userId', 'integer', 'min:1'],
            'subjectType' => ['required_with:subjectId', 'string', 'in:USER'],
            'expectedContextVersion' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function subjectId(): int
    {
        return $this->filled('subjectId') ? $this->integer('subjectId') : $this->integer('userId');
    }
}
