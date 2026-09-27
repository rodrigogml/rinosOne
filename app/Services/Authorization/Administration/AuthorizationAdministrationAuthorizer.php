<?php

namespace App\Services\Authorization\Administration;

use App\Domain\Authorization\Administration\AuthorizationAdministrationAccessDeniedException;
use App\Domain\Authorization\AuthorizationScope;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;

/**
 * Resolves the administrative permission applicable to a protected tenant context.
 *
 * Tenant administrators must hold an active membership; platform supervision is an
 * independent PLATFORM grant and never implies membership in the target tenant.
 */
class AuthorizationAdministrationAuthorizer
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function assertCanRead(User $actor, int $tenantId): void
    {
        $this->assertAllowed($actor, $tenantId, 'read');
    }

    public function assertCanManage(User $actor, int $tenantId): void
    {
        $this->assertAllowed($actor, $tenantId, 'manage');
    }

    private function assertAllowed(User $actor, int $tenantId, string $operation): void
    {
        if ($tenantId < 1) {
            throw new AuthorizationAdministrationAccessDeniedException('Authorization administration access is denied.');
        }

        if ($this->authorization->check($actor, 'tenant.authorization.'.$operation, AuthorizationScope::Tenant, $tenantId)->allowed
            || $this->authorization->check($actor, 'platform.authorization.tenant.'.$operation, AuthorizationScope::Platform)->allowed) {
            return;
        }

        throw new AuthorizationAdministrationAccessDeniedException('Authorization administration access is denied.');
    }
}
