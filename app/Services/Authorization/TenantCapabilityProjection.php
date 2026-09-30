<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Person\PersonPermission;
use App\Models\User;

class TenantCapabilityProjection
{
    /**
     * Projects only the capabilities needed by a tenant-facing surface from current authorization decisions.
     *
     * @return array<string, bool>
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
            'canReadAuthorization' => $this->authorization->check(
                $principal,
                'tenant.authorization.read',
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed || $this->authorization->check(
                $principal,
                'platform.authorization.tenant.read',
                AuthorizationScope::Platform,
            )->allowed,
            'canReadPeople' => $this->authorization->check(
                $principal,
                PersonPermission::READ,
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
            'canCreatePeople' => $this->authorization->check(
                $principal,
                PersonPermission::CREATE,
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
            'canUpdatePeople' => $this->authorization->check(
                $principal,
                PersonPermission::UPDATE,
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
            'canDuplicatePeople' => $this->authorization->check(
                $principal,
                PersonPermission::DUPLICATE,
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
            'canInactivatePeople' => $this->authorization->check(
                $principal,
                PersonPermission::INACTIVATE,
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
            'canReactivatePeople' => $this->authorization->check(
                $principal,
                PersonPermission::REACTIVATE,
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
            'canDeletePeople' => $this->authorization->check(
                $principal,
                PersonPermission::DELETE,
                AuthorizationScope::Tenant,
                $tenantId,
            )->allowed,
        ];
    }

    public function __construct(private readonly AuthorizationService $authorization) {}
}
