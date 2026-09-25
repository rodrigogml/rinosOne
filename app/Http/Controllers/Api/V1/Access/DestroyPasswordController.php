<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Domain\Access\Account\AccountEligibilityService;
use Illuminate\Http\Response;

class DestroyPasswordController
{
    public function __invoke(AccountEligibilityService $eligibility): Response
    {
        $user = request()->user();
        abort_unless($eligibility->canAuthenticate($user), 403);

        $user->forceFill(['passwordHash' => null])->save();

        return response()->noContent();
    }
}
