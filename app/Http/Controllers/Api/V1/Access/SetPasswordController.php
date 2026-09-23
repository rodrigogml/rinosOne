<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Domain\Access\Account\AccountEligibilityService;
use App\Domain\Access\Credential\PasswordStrengthPolicy;
use App\Http\Requests\Access\SetPasswordRequest;
use Illuminate\Http\Response;

class SetPasswordController
{
    public function __invoke(SetPasswordRequest $request, AccountEligibilityService $eligibility, PasswordStrengthPolicy $policy): Response
    {
        $user = $request->user();
        abort_unless($eligibility->canAuthenticate($user), 403);
        if (! $policy->isSatisfiedBy($request->string('password')->toString())) {
            abort(422, 'A senha não atende aos critérios de segurança.');
        }
        $user->forceFill(['passwordHash' => $request->string('password')->toString()])->save();

        return response()->noContent();
    }
}
