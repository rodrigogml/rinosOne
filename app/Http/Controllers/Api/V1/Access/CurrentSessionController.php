<?php

namespace App\Http\Controllers\Api\V1\Access;

use Illuminate\Http\JsonResponse;

class CurrentSessionController
{
    public function __invoke(): JsonResponse
    {
        $user = request()->user();

        return response()->json([
            'user' => ['id' => $user->id, 'displayName' => $user->displayName, 'passwordDefined' => $user->passwordHash !== null],
            'persistentAuthentication' => request()->session()->get('persistentAuthenticationId') !== null,
        ]);
    }
}
