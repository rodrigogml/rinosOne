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
        return ['challengeId' => ['required', 'integer', 'min:1'], 'code' => ['required', 'string', 'digits:6']];
    }
}
