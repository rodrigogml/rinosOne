<?php

namespace App\Services\Authorization\Administration;

use App\Domain\Authorization\AuthorizationScope;
use InvalidArgumentException;

/**
 * Carries an authorization-administration context derived from an authenticated route.
 *
 * Tenant contexts require a BIGINT tenant identifier; personal and platform contexts
 * intentionally have no tenant identifier to prevent cross-scope reuse.
 */
readonly class AuthorizationAdministrationContext
{
    public function __construct(
        public AuthorizationScope $scope,
        public ?int $tenantId,
        public AuthorizationAdministrationCapabilities $capabilities,
    ) {
        if ($scope === AuthorizationScope::Tenant && ($tenantId === null || $tenantId < 1)) {
            throw new InvalidArgumentException('Tenant authorization administration requires a valid tenant context.');
        }

        if ($scope !== AuthorizationScope::Tenant && $tenantId !== null) {
            throw new InvalidArgumentException('Only tenant authorization administration may include a tenant context.');
        }
    }
}
