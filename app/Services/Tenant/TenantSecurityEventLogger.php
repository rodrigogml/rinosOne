<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\TenantSecurityEvent;
use Illuminate\Support\Facades\Log;

class TenantSecurityEventLogger
{
    public function record(TenantSecurityEvent $event, string $userId): void
    {
        Log::channel('security')->info('security.tenant.event', ['event' => $event->value, 'userId' => $userId]);
    }
}
