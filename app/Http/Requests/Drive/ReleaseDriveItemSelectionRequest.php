<?php

namespace App\Http\Requests\Drive;

class ReleaseDriveItemSelectionRequest extends DriveItemSelectionRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...parent::rules(), 'confirmation' => ['required', 'accepted']];
    }
}
