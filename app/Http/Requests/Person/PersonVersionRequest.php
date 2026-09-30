<?php

namespace App\Http\Requests\Person;

abstract class PersonVersionRequest extends PersonApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1']];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('version');
    }
}
