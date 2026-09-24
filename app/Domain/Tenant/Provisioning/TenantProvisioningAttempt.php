<?php

namespace App\Domain\Tenant\Provisioning;

readonly class TenantProvisioningAttempt
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public int $attemptCount,
    ) {}
}
