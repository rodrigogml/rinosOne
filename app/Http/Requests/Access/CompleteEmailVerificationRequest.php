<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class CompleteEmailVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['challengeId' => ['required', 'string', 'ulid'], 'code' => ['required', 'string', 'digits:6'], 'displayName' => ['required', 'string', 'max:255', 'not_regex:/^\\s*$/u']];
    }
}
