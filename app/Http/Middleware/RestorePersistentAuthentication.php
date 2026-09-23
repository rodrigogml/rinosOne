<?php

namespace App\Http\Middleware;

use App\Domain\Access\Account\AccountEligibilityService;
use App\Models\PersistentAuthentication;
use App\Services\Access\AuthenticationSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class RestorePersistentAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            $value = $request->cookie(config('access.authentication.persistentCookieName'));
            [$id, $secret] = is_string($value) && str_contains($value, '.') ? explode('.', $value, 2) : [null, null];
            $authentication = $id === null ? null : PersistentAuthentication::query()->whereNull('revokedAt')->find($id);
            if ($authentication !== null && $secret !== null && Hash::check($secret, $authentication->secretHash)) {
                $user = $authentication->user;
                if ($user !== null && app(AccountEligibilityService::class)->canAuthenticate($user)) {
                    app(AuthenticationSessionService::class)->restore($user, $authentication->id);
                    $authentication->forceFill(['lastUsedAt' => now()])->save();
                }
            }
        }

        return $next($request);
    }
}
