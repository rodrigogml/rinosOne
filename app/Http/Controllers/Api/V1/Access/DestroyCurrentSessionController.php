<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Models\PersistentAuthentication;
use App\Services\Access\ServerSessionLifecycleService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class DestroyCurrentSessionController
{
    public function __invoke(ServerSessionLifecycleService $sessions): Response
    {
        $session = request()->session();
        $persistentAuthenticationId = $session->get('persistentAuthenticationId');
        $sessions->discard($session->getId());
        if ($persistentAuthenticationId !== null) {
            PersistentAuthentication::query()->whereKey($persistentAuthenticationId)->update(['revokedAt' => now()]);
        }
        Auth::logout();
        $session->invalidate();
        $session->regenerateToken();

        return response()->noContent()->withCookie(Cookie::forget(config('access.authentication.persistentCookieName')));
    }
}
