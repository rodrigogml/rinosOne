<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\TenantMembershipRole;
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
     * @return array{tenant: array{id: string, displayName: string}, membership: array{id: string, role: string}, availableModules: array<never, never>}
     */
    public function context(TenantMembership $membership): array
    {
        return [
            'tenant' => ['id' => $membership->tenant->id, 'displayName' => $membership->tenant->displayName],
            'membership' => ['id' => $membership->id, 'role' => $membership->role->value],
            'availableModules' => [],
        ];
    }

    public function isOwner(TenantMembership $membership): bool
    {
        return $membership->role === TenantMembershipRole::Owner;
    }
}
