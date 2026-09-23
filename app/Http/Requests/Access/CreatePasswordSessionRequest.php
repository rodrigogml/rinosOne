<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class CreatePasswordSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc'], 'password' => ['required', 'string'], 'rememberMe' => ['sometimes', 'boolean']];
    }
}
