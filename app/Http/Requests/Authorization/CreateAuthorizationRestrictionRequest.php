<?php

namespace App\Http\Requests\Authorization;

class CreateAuthorizationRestrictionRequest extends AdministrativeAuthorizationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permissionId' => ['required', 'integer', 'min:1'], 'userId' => ['nullable', 'integer', 'min:1'], 'groupId' => ['nullable', 'integer', 'min:1'], 'startsAt' => ['nullable', 'date'], 'endsAt' => ['nullable', 'date', 'after:startsAt']];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->filled('userId') === $this->filled('groupId')) {
                $validator->errors()->add('subject', 'Informe exatamente um sujeito para a restriction.');
            }
        }];
    }
}
