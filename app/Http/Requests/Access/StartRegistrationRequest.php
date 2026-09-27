<?php

namespace App\Http\Requests\Access;

use App\Domain\Access\Identity\DisplayNameValidationRules;
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
            'displayName' => DisplayNameValidationRules::rules(),
            'email' => ['required', 'string', 'email:rfc'],
            'rememberMe' => ['sometimes', 'boolean'],
        ];
    }
}
