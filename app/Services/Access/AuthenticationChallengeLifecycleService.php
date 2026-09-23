<?php

namespace App\Services\Access;

use App\Models\AuthenticationChallenge;

class AuthenticationChallengeLifecycleService
{
    public function discard(AuthenticationChallenge $challenge): void
    {
        $challenge->delete();
    }

    public function discardForUserAndPurpose(string $userId, string $purpose): int
    {
        return AuthenticationChallenge::query()
            ->where('idUser', $userId)
            ->where('purpose', $purpose)
            ->delete();
    }

    public function discardExpired(): int
    {
        return AuthenticationChallenge::query()
            ->where('expiresAt', '<=', now())
            ->delete();
    }
}
