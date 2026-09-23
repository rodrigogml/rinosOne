<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPasswordlessSessionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['challengeId' => ['required', 'string', 'ulid'], 'token' => ['required', 'string']];
    }
}
