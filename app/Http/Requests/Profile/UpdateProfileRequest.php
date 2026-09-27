<?php

namespace App\Http\Requests\Profile;

use App\Domain\Access\Identity\DisplayNameValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'displayName' => DisplayNameValidationRules::rules(),
        ];
    }
}
