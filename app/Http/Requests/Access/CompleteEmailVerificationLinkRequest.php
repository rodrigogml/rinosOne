<?php

namespace App\Http\Requests\Access;

class CompleteEmailVerificationLinkRequest extends CompleteEmailVerificationRequest
{
    public function rules(): array
    {
        return ['challengeId' => ['required', 'string', 'ulid'], 'token' => ['required', 'string']];
    }
}
