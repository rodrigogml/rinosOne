<?php

namespace App\Domain\Tenant;

use App\Models\Tenant;
use App\Models\TenantProvisioning;

readonly class TenantCreationResult
{
    public function __construct(
        public Tenant $tenant,
        public TenantProvisioning $provisioning,
        public bool $created,
    ) {}
}
