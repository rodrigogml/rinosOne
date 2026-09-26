<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationAssignmentState;
use App\Domain\Tenant\TenantMembershipState;
use App\Models\AuthorizationRoleAssignment;
use App\Models\User;
use LogicException;

class TenantAdministratorInvariant
{
    public function isActiveDirectAdministrator(User $user, int $tenantId): bool
    {
        return AuthorizationRoleAssignment::query()
            ->join('auth_role', 'auth_role.id', '=', 'auth_role_assignment.idRole')
            ->where('auth_role.key', 'tenant.administrator')
            ->where('auth_role_assignment.idUser', $user->id)
            ->where('auth_role_assignment.idTenant', $tenantId)
            ->where('auth_role_assignment.state', AuthorizationAssignmentState::Active->value)
            ->exists();
    }

    public function ensureAnotherActiveDirectAdministrator(int $tenantId, ?int $excludedAssignmentId = null, ?int $excludedUserId = null): void
    {
        $remaining = AuthorizationRoleAssignment::query()
            ->join('auth_role', 'auth_role.id', '=', 'auth_role_assignment.idRole')
            ->where('auth_role.key', 'tenant.administrator')
            ->where('auth_role_assignment.idTenant', $tenantId)
            ->where('auth_role_assignment.state', AuthorizationAssignmentState::Active->value)
            ->when($excludedAssignmentId !== null, fn ($query) => $query->where('auth_role_assignment.id', '!=', $excludedAssignmentId))
            ->when($excludedUserId !== null, fn ($query) => $query->where('auth_role_assignment.idUser', '!=', $excludedUserId))
            ->whereExists(function ($query): void {
                $query->selectRaw('1')->from('tenantMembership')
                    ->whereColumn('tenantMembership.idTenant', 'auth_role_assignment.idTenant')
                    ->whereColumn('tenantMembership.idUser', 'auth_role_assignment.idUser')
                    ->where('tenantMembership.state', TenantMembershipState::Active->value);
            })
            ->lockForUpdate()
            ->exists();

        if (! $remaining) {
            throw new LogicException('A tenant must retain an active direct administrator assignment.');
        }
    }
}
