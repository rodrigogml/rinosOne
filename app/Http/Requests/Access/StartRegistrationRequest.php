<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class StartRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'displayName' => ['required', 'string', 'max:255', 'not_regex:/^\\s*$/u'],
            'email' => ['required', 'string', 'email:rfc'],
            'rememberMe' => ['sometimes', 'boolean'],
        ];
    }
}
