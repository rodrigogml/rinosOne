<?php

namespace App\Services\Authorization\Administration;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use InvalidArgumentException;

/**
 * Resolves the administrative capabilities of an authenticated user for a route-bound scope.
 *
 * Callers use one of the explicit scope methods instead of supplying a free-form scope or
 * workspace identifier, so a descriptor cannot be reused to select a broader context.
 */
class AuthorizationAdministrationContextResolver
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function forPersonal(User $actor): AuthorizationAdministrationContext
    {
        $this->assertPersistedActor($actor);

        return new AuthorizationAdministrationContext(
            AuthorizationScope::Personal,
            null,
            new AuthorizationAdministrationCapabilities(
                canReadAccess: true,
                canManageRoles: false,
                canManageSharing: true,
                canUseAdvancedControls: false,
            ),
        );
    }

    public function forTenant(User $actor, int $tenantId): AuthorizationAdministrationContext
    {
        $this->assertPersistedActor($actor);
        if ($tenantId < 1) {
            throw new InvalidArgumentException('Tenant authorization administration requires a valid tenant context.');
        }

        $canRead = $this->canAdministerTenant($actor, $tenantId, 'read');
        $canManage = $this->canAdministerTenant($actor, $tenantId, 'manage');

        return new AuthorizationAdministrationContext(
            AuthorizationScope::Tenant,
            $tenantId,
            new AuthorizationAdministrationCapabilities(
                canReadAccess: $canRead,
                canManageRoles: $canManage,
                canManageSharing: $canManage,
                canUseAdvancedControls: $canManage,
            ),
        );
    }

    public function forPlatform(User $actor): AuthorizationAdministrationContext
    {
        $this->assertPersistedActor($actor);
        $canRead = $this->authorization->check(
            $actor,
            'platform.authorization.tenant.read',
            AuthorizationScope::Platform,
        )->allowed;
        $canManage = $this->authorization->check(
            $actor,
            'platform.authorization.tenant.manage',
            AuthorizationScope::Platform,
        )->allowed;

        return new AuthorizationAdministrationContext(
            AuthorizationScope::Platform,
            null,
            new AuthorizationAdministrationCapabilities(
                canReadAccess: $canRead,
                canManageRoles: $canManage,
                canManageSharing: false,
                canUseAdvancedControls: $canManage,
            ),
        );
    }

    private function canAdministerTenant(User $actor, int $tenantId, string $operation): bool
    {
        return $this->authorization->check(
            $actor,
            'tenant.authorization.'.$operation,
            AuthorizationScope::Tenant,
            $tenantId,
        )->allowed || $this->authorization->check(
            $actor,
            'platform.authorization.tenant.'.$operation,
            AuthorizationScope::Platform,
        )->allowed;
    }

    private function assertPersistedActor(User $actor): void
    {
        if (! is_int($actor->getKey()) || $actor->getKey() < 1) {
            throw new InvalidArgumentException('Authorization administration requires an authenticated persisted user.');
        }
    }
}
