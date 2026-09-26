<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\TenantMembership;
use App\Models\User;

class TenantContextService
{
    public function findSelectable(User $user, string $tenantId): ?TenantMembership
    {
        return TenantMembership::query()
            ->with('tenant')
            ->where('idUser', $user->id)
            ->where('idTenant', $tenantId)
            ->where('state', TenantMembershipState::Active)
            ->whereHas('tenant', fn ($query) => $query->where('state', TenantState::Active))
            ->first();
    }

    /**
     * @param  array{canManageAvailability: bool}  $capabilities
     * @return array{tenant: array{id: int, displayName: string}, membership: array{id: int}, capabilities: array{canManageAvailability: bool}, availableModules: array<never, never>}
     */
    public function context(TenantMembership $membership, array $capabilities): array
    {
        return [
            'tenant' => ['id' => $membership->tenant->id, 'displayName' => $membership->tenant->displayName],
            'membership' => ['id' => $membership->id],
            'capabilities' => $capabilities,
            'availableModules' => [],
        ];
    }
}
