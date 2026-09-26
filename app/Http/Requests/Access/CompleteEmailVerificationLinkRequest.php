<?php

namespace App\Http\Requests\Access;

class CompleteEmailVerificationLinkRequest extends CompleteEmailVerificationRequest
{
    public function rules(): array
    {
        return ['challengeId' => ['required', 'integer', 'min:1'], 'token' => ['required', 'string']];
    }
}
