<?php

namespace App\Infrastructure\Session;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Session\DatabaseSessionHandler as LaravelDatabaseSessionHandler;
use Illuminate\Support\Carbon;

class DatabaseSessionHandler extends LaravelDatabaseSessionHandler
{
    protected function expired($session): bool
    {
        if ($this->minutes <= 0 || ! isset($session->lastActivityAt)) {
            return false;
        }

        return Carbon::parse($session->lastActivityAt)->lt(Carbon::now()->subMinutes($this->minutes));
    }

    protected function getDefaultPayload($data): array
    {
        $payload = [
            'payload' => base64_encode($data),
            'lastActivityAt' => Carbon::now(),
        ];

        if ($this->container?->bound(Guard::class)) {
            $payload['idUser'] = $this->userId();
        }
        $payload['idPersistentAuthentication'] = $this->container?->make('request')->session()->get('persistentAuthenticationId');

        return $payload;
    }

    public function gc($lifetime): int
    {
        if ($lifetime <= 0) {
            return 0;
        }

        return $this->getQuery()
            ->where('lastActivityAt', '<=', Carbon::now()->subSeconds($lifetime))
            ->delete();
    }
}
