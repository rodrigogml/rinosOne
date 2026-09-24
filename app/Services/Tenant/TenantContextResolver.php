<?php

namespace App\Services\Tenant;

use App\Infrastructure\Tenant\TenantDatabaseConnectionFactory;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

class TenantContextResolver
{
    public function __construct(
        private readonly TenantContextService $contexts,
        private readonly TenantDatabaseConnectionFactory $connections,
    ) {}

    public function resolveMembership(User $user, string $tenantId): ?TenantMembership
    {
        return $this->contexts->findSelectable($user, $tenantId);
    }

    public function resolveConnection(User $user, string $tenantId): ?ConnectionInterface
    {
        if ($this->resolveMembership($user, $tenantId) === null) {
            return null;
        }

        return $this->connections->connection($tenantId);
    }
}
