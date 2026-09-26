<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationAssignmentState;
use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationGroupRoleAssignment;
use App\Models\AuthorizationRole;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationGroupRoleAssignmentService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit) {}

    public function grant(AuthorizationRole $role, AuthorizationGroup $group, ?int $actorUserId = null, ?string $correlationId = null): AuthorizationGroupRoleAssignment
    {
        return DB::transaction(function () use ($role, $group, $actorUserId, $correlationId): AuthorizationGroupRoleAssignment {
            $role = AuthorizationRole::query()->lockForUpdate()->findOrFail($role->id);
            $group = AuthorizationGroup::query()->lockForUpdate()->findOrFail($group->id);
            $tenantId = $this->validateCompatibility($role, $group);
            $assignment = AuthorizationGroupRoleAssignment::query()->firstOrNew([
                'idRole' => $role->id,
                'idGroup' => $group->id,
            ]);
            $before = $assignment->exists ? ['state' => $assignment->state] : null;
            $assignment->idTenant = $tenantId;
            $assignment->state = AuthorizationAssignmentState::Active->value;
            $assignment->save();

            if ($assignment->wasRecentlyCreated || $assignment->wasChanged('state')) {
                $this->audit->record(
                    'authorization.group_role_assignment.activated',
                    'authorization.group_role_assignment',
                    $assignment->id,
                    actorUserId: $actorUserId,
                    tenantId: $tenantId,
                    before: $before,
                    after: ['idRole' => $role->id, 'idGroup' => $group->id, 'state' => $assignment->state],
                    correlationId: $correlationId,
                );
            }

            return $assignment;
        });
    }

    public function deactivate(AuthorizationRole $role, AuthorizationGroup $group, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($role, $group, $actorUserId, $correlationId): void {
            $assignment = AuthorizationGroupRoleAssignment::query()
                ->where('idRole', $role->id)
                ->where('idGroup', $group->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($assignment->state === AuthorizationAssignmentState::Inactive->value) {
                return;
            }

            $before = ['state' => $assignment->state];
            $assignment->state = AuthorizationAssignmentState::Inactive->value;
            $assignment->save();
            $this->audit->record(
                'authorization.group_role_assignment.deactivated',
                'authorization.group_role_assignment',
                $assignment->id,
                actorUserId: $actorUserId,
                tenantId: $assignment->idTenant,
                before: $before,
                after: ['state' => $assignment->state],
                correlationId: $correlationId,
            );
        });
    }

    public function remove(AuthorizationRole $role, AuthorizationGroup $group, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($role, $group, $actorUserId, $correlationId): void {
            $assignment = AuthorizationGroupRoleAssignment::query()
                ->where('idRole', $role->id)
                ->where('idGroup', $group->id)
                ->lockForUpdate()
                ->firstOrFail();
            $before = ['idRole' => $assignment->idRole, 'idGroup' => $assignment->idGroup, 'state' => $assignment->state];
            $assignment->delete();
            $this->audit->record(
                'authorization.group_role_assignment.removed',
                'authorization.group_role_assignment',
                $assignment->id,
                actorUserId: $actorUserId,
                tenantId: $assignment->idTenant,
                before: $before,
                correlationId: $correlationId,
            );
        });
    }

    private function validateCompatibility(AuthorizationRole $role, AuthorizationGroup $group): ?int
    {
        if (! $role->active || ! $group->active) {
            throw new LogicException('Inactive authorization roles and groups cannot receive grants.');
        }

        if ($role->scope !== $group->scope) {
            throw new LogicException('Authorization role and group scopes must match.');
        }

        if ($role->scope === AuthorizationScope::Tenant->value && $group->idTenant === null) {
            throw new LogicException('A tenant group role assignment requires a tenant context.');
        }

        if ($role->idTenant !== null && $role->idTenant !== $group->idTenant) {
            throw new LogicException('A tenant-owned role cannot be granted to a group from another tenant.');
        }

        if ($role->scope !== AuthorizationScope::Tenant->value && $group->idTenant !== null) {
            throw new LogicException('Only tenant group role assignments can have a tenant context.');
        }

        return $group->idTenant;
    }
}
