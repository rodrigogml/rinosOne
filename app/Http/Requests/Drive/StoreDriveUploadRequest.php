<?php

namespace App\Http\Requests\Drive;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriveUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file'],
            'parentFolderId' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
