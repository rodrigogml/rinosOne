<?php

namespace App\Http\Requests\Person;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Services\Person\PersonAdvancedFilterCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validates a lazy, tenant-scoped People catalogue query. */
class QueryPeopleRequest extends PersonApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:512'],
            'advancedFilter' => ['nullable', 'array'],
            'personType' => ['nullable', 'string', 'in:PF,PJ'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE'],
            'sortBy' => ['nullable', Rule::in(['displayName', 'personType', 'document', 'status'])],
            'sortDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            'sorts' => ['nullable', 'array', 'max:3'],
            'sorts.*.column' => ['required_with:sorts', Rule::in(['displayName', 'personType', 'document', 'status'])],
            'sorts.*.direction' => ['required_with:sorts', Rule::in(['asc', 'desc'])],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'includeIds' => ['nullable', 'array', 'max:10000'],
            'includeIds.*' => ['integer', 'min:1'],
            'selectedIds' => ['nullable', 'array', 'max:10000'],
            'selectedIds.*' => ['integer', 'min:1'],
            'selectedOnly' => ['nullable', 'boolean'],
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

    public function personType(): ?PersonType
    {
        $value = $this->validated('personType');

        return $value === null ? null : PersonType::from($value);
    }

    public function status(): PersonStatus
    {
        return PersonStatus::from($this->validated('status') ?? PersonStatus::ACTIVE->value);
    }

    public function sortBy(): string { return (string) ($this->validated('sortBy') ?? 'displayName'); }
    public function sortDirection(): string { return (string) ($this->validated('sortDirection') ?? 'asc'); }
    /** @return list<array{column: string, direction: string}> */
    public function sorts(): array
    {
        $sorts = $this->validated('sorts');
        if (! is_array($sorts) || $sorts === []) {
            return [['column' => $this->sortBy(), 'direction' => $this->sortDirection()]];
        }

        $unique = [];
        foreach ($sorts as $sort) {
            $column = (string) $sort['column'];
            if (! isset($unique[$column])) {
                $unique[$column] = ['column' => $column, 'direction' => (string) $sort['direction']];
            }
        }

        return array_values($unique);
    }
    public function offset(): int { return (int) ($this->validated('offset') ?? 0); }
    public function limit(): int { return (int) ($this->validated('limit') ?? 100); }
    /** @return list<int> */
    public function includeIds(): array { return array_values(array_unique(array_map('intval', $this->validated('includeIds') ?? []))); }
    /** @return list<int> */
    public function selectedIds(): array { return array_values(array_unique(array_map('intval', $this->validated('selectedIds') ?? []))); }
    public function selectedOnly(): bool { return (bool) ($this->validated('selectedOnly') ?? false); }
}
