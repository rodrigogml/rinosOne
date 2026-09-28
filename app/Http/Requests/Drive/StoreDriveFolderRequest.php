<?php

namespace App\Http\Requests\Drive;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriveFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['displayName' => ['required', 'string', 'max:160'], 'parentFolderId' => ['nullable', 'integer', 'min:1']];
    }
}
