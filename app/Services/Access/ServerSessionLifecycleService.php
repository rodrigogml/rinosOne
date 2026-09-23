<?php

namespace App\Services\Access;

use Illuminate\Support\Facades\DB;

class ServerSessionLifecycleService
{
    public function discard(string $sessionId): bool
    {
        return DB::table('session')
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function discardOtherSessions(string $userId, string $currentSessionId): int
    {
        return DB::table('session')->where('idUser', $userId)->where('id', '!=', $currentSessionId)->delete();
    }
}
