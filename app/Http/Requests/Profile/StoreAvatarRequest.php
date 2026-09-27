<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file'],
            'cropX' => ['required', 'numeric'],
            'cropY' => ['required', 'numeric'],
            'cropSize' => ['required', 'numeric'],
        ];
    }
}
