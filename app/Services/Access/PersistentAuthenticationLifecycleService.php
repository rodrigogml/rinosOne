<?php

namespace App\Services\Access;

use App\Models\PersistentAuthentication;

final class PersistentAuthenticationLifecycleService
{
    public function revokeOthers(string $userId, ?string $currentAuthenticationId): int
    {
        $query = PersistentAuthentication::query()->where('idUser', $userId)->whereNull('revokedAt');
        if ($currentAuthenticationId !== null) {
            $query->where('id', '!=', $currentAuthenticationId);
        }

        return $query->update(['revokedAt' => now()]);
    }
}
