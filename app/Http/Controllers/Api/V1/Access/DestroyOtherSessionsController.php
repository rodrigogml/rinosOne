<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Services\Access\PersistentAuthenticationLifecycleService;
use App\Services\Access\ServerSessionLifecycleService;
use Illuminate\Http\Response;

class DestroyOtherSessionsController
{
    public function __invoke(ServerSessionLifecycleService $sessions, PersistentAuthenticationLifecycleService $persistentAuthentications): Response
    {
        $session = request()->session();
        $user = request()->user();
        $sessions->discardOtherSessions($user->id, $session->getId());
        $persistentAuthentications->revokeOthers($user->id, $session->get('persistentAuthenticationId'));

        return response()->noContent();
    }
}
