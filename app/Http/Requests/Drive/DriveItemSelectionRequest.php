<?php

namespace App\Http\Requests\Drive;

use Illuminate\Foundation\Http\FormRequest;

class DriveItemSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['items' => ['required', 'array', 'min:1'], 'items.*.type' => ['required', 'in:folder,file'], 'items.*.id' => ['required', 'integer', 'min:1']];
    }

    /** @return list<array{type: string, id: int}> */
    public function items(): array
    {
        return array_map(fn (array $item): array => ['type' => $item['type'], 'id' => (int) $item['id']], $this->validated('items'));
    }
}
