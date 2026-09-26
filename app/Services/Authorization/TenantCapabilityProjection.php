<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\User;

class TenantCapabilityProjection
{
    /**
     * Projects only the capabilities needed by a tenant-facing surface from current authorization decisions.
     *
     * @return array{canManageAvailability: bool}
     */
    public function forTenant(User $principal, int $tenantId): array
    {
        return [
            'canManageAvailability' => $this->authorization->check(
                $principal,
                'tenant.availability.manage',
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
        ];
    }

    public function __construct(private readonly AuthorizationService $authorization) {}
}
