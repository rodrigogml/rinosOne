<?php

namespace App\Domain\Tenant\Provisioning;

class TenantProvisioningPolicy
{
    public function maximumAttempts(): int
    {
        return max(1, (int) config('access.tenantProvisioning.maximumAttempts'));
    }

    public function retryDelaySeconds(int $attemptCount): ?int
    {
        $delays = config('access.tenantProvisioning.retryDelaysMinutes');

        if (! is_array($delays) || $attemptCount < 1 || ! array_key_exists($attemptCount - 1, $delays)) {
            return null;
        }

        $delay = (int) $delays[$attemptCount - 1];

        return $delay > 0 ? $delay * 60 : null;
    }
}
