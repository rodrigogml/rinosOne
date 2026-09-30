<?php

namespace App\Http\Requests\Person;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;

class IndexPersonRequest extends PersonApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:512'],
            'personType' => ['nullable', 'string', 'in:PF,PJ'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.config('api.pagination.maximumPerPage')],
        ];
    }

    public function pageNumber(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->validated('perPage') ?? config('api.pagination.defaultPerPage'));
    }

    public function personType(): ?PersonType
    {
        $value = $this->validated('personType');

        return $value === null ? null : PersonType::from($value);
    }

    public function status(): PersonStatus
    {
        return PersonStatus::from($this->validated('status') ?? PersonStatus::ACTIVE->value);
    }

    public function search(): ?string
    {
        $value = trim((string) ($this->validated('search') ?? ''));

        return $value === '' ? null : $value;
    }
}
