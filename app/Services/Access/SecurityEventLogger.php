<?php

namespace App\Services\Access;

use App\Domain\Access\Security\SecurityEvent;
use Illuminate\Support\Facades\Log;

class SecurityEventLogger
{
    public function record(SecurityEvent $event, ?string $userId = null): void
    {
        Log::channel('security')->info('security.access.event', [
            'event' => $event->value,
            'userId' => $userId,
        ]);
    }
}
