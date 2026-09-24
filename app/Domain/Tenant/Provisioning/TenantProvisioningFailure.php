<?php

namespace App\Domain\Tenant\Provisioning;

readonly class TenantProvisioningFailure
{
    public function __construct(
        public ProvisioningFailureClassification $classification,
        public string $code,
    ) {}
}
