<?php

namespace App\Http\Requests\Person;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Services\Person\PersonAdvancedFilterCatalog;
use Illuminate\Validation\Validator;

/** Validates the explicit ID resolution used by Select all in a People list. */
class ResolvePersonSelectionRequest extends PersonApiRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:512'],
            'advancedFilter' => ['nullable', 'array'],
            'personType' => ['nullable', 'string', 'in:PF,PJ'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (app(PersonAdvancedFilterCatalog::class)->errors($this->input('advancedFilter')) as $attribute => $messages) {
                foreach ($messages as $message) $validator->errors()->add($attribute, $message);
            }
        });
    }

    public function search(): ?string
    {
        $value = trim((string) ($this->validated('search') ?? ''));
        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed>|null */
    public function advancedFilter(): ?array
    {
        $filter = $this->input('advancedFilter');

        return is_array($filter) ? $filter : null;
    }
    public function personType(): ?PersonType { $value = $this->validated('personType'); return $value === null ? null : PersonType::from($value); }
    public function status(): PersonStatus { return PersonStatus::from($this->validated('status') ?? PersonStatus::ACTIVE->value); }
}
