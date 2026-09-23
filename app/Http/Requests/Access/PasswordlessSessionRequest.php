<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class PasswordlessSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc'], 'rememberMe' => ['sometimes', 'boolean']];
    }
}
