<?php

namespace App\Services\Access;

use App\Models\PersistentAuthentication;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

final class AuthenticationSessionService
{
    public function start(User $user, bool $rememberMe): ?HttpCookie
    {
        Auth::login($user);
        request()->session()->regenerate();

        if (! $rememberMe) {
            return null;
        }
        $secret = bin2hex(random_bytes(32));
        $authentication = PersistentAuthentication::query()->create(['idUser' => $user->id, 'secretHash' => Hash::make($secret), 'lastUsedAt' => now()]);
        request()->session()->put('persistentAuthenticationId', $authentication->id);
        $minutes = config('access.authentication.persistentLoginLifetimeDays') * 1440;

        return $minutes > 0
            ? Cookie::make(config('access.authentication.persistentCookieName'), $authentication->id.'.'.$secret, $minutes, null, null, null, true, false, 'lax')
            : Cookie::forever(config('access.authentication.persistentCookieName'), $authentication->id.'.'.$secret, null, null, null, true, false, 'lax');
    }

    public function restore(User $user, string $persistentAuthenticationId): void
    {
        Auth::login($user);
        request()->session()->regenerate();
        request()->session()->put('persistentAuthenticationId', $persistentAuthenticationId);
    }
}
