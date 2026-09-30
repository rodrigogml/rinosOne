<?php

namespace App\Services\Person;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Person\Exception\PersonAccessDeniedException;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Tenant\TenantContextResolver;
use Illuminate\Database\ConnectionInterface;

/**
 * Resolves the only database connection a People operation may use.
 *
 * It validates active tenant membership and the operation capability before a
 * tenant schema connection is obtained. The service keeps no selected-tenant
 * state, so each operation is bound to its explicit tenant context.
 */
class PersonTenantContext
{
    public function __construct(
        private readonly TenantContextResolver $tenants,
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * @throws PersonAccessDeniedException when the tenant context is unavailable or the capability is denied.
     */
    public function connectionFor(User $principal, int $tenantId, string $permission): ConnectionInterface
    {
        if ($this->tenants->resolveMembership($principal, (string) $tenantId) === null) {
            throw new PersonAccessDeniedException;
        }

        if (! $this->authorization->check($principal, $permission, AuthorizationScope::Tenant, $tenantId)->allowed) {
            throw new PersonAccessDeniedException;
        }

        $connection = $this->tenants->resolveConnection($principal, (string) $tenantId);

        if ($connection === null) {
            throw new PersonAccessDeniedException;
        }

        return $connection;
    }
}
