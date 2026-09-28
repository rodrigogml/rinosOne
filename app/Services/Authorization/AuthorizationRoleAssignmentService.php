<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationAssignmentState;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Performance\PolicyVersionService;
use App\Services\Authorization\Advanced\AuthorizationSeparationEvaluator;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationRoleAssignmentService
{
    public function __construct(
        private readonly AuthorizationAuditLogger $audit,
        private readonly TenantAdministratorInvariant $administrators,
        private readonly PolicyVersionService $policyVersions,
        private readonly AuthorizationSeparationEvaluator $separationEvaluator,
    ) {}

    public function assignTenantRole(AuthorizationRole $role, User $user, int $tenantId, ?int $actorUserId = null, ?string $correlationId = null): AuthorizationRoleAssignment
    {
        if (! $role->active || $role->scope !== AuthorizationScope::Tenant->value) {
            throw new LogicException('Only active tenant roles can be assigned in a tenant context.');
        }
        if ($role->idTenant !== null && $role->idTenant !== $tenantId) {
            throw new LogicException('A tenant-owned role cannot be assigned outside its tenant.');
        }

        $eligible = TenantMembership::query()->where('idTenant', $tenantId)->where('idUser', $user->id)->where('state', TenantMembershipState::Active)->exists();
        if (! $eligible) {
            throw new LogicException('A tenant role requires an active tenant membership.');
        }

        return DB::transaction(function () use ($role, $user, $tenantId, $actorUserId, $correlationId): AuthorizationRoleAssignment {
            TenantMembership::query()
                ->where('idTenant', $tenantId)
                ->where('idUser', $user->id)
                ->where('state', TenantMembershipState::Active)
                ->lockForUpdate()
                ->firstOrFail();
            $this->separationEvaluator->assertDirectRoleAssignmentAllowed($role, $user, $tenantId);
            $assignment = AuthorizationRoleAssignment::query()->firstOrNew([
                'idRole' => $role->id,
                'idUser' => $user->id,
                'idTenant' => $tenantId,
            ]);
            $before = $assignment->exists ? ['state' => $assignment->state] : null;
            $assignment->state = AuthorizationAssignmentState::Active->value;
            $assignment->save();

            if ($assignment->wasRecentlyCreated || $assignment->wasChanged('state')) {
                $this->audit->record(
                    'authorization.role_assignment.activated',
                    'authorization.role_assignment',
                    $assignment->id,
                    actorUserId: $actorUserId,
                    tenantId: $tenantId,
                    before: $before,
                    after: ['idRole' => $role->id, 'idUser' => $user->id, 'state' => $assignment->state],
                    correlationId: $correlationId,
                );
                $this->policyVersions->invalidate(AuthorizationScope::Tenant, $tenantId);
            }

            return $assignment;
        });
    }

    public function assignTenantAdministrator(User $user, int $tenantId, ?int $actorUserId = null, ?string $correlationId = null): AuthorizationRoleAssignment
    {
        return DB::transaction(function () use ($user, $tenantId, $actorUserId, $correlationId): AuthorizationRoleAssignment {
            $role = $this->administratorRole();

            return $this->assignTenantRole($role, $user, $tenantId, $actorUserId, $correlationId);
        });
    }

    public function transferTenantAdministrator(User $from, User $to, int $tenantId): void
    {
        DB::transaction(function () use ($from, $to, $tenantId): void {
            $role = $this->administratorRole();
            $this->assignTenantRole($role, $to, $tenantId);
            $this->deactivate($role, $from, $tenantId);
        });
    }

    public function deactivate(AuthorizationRole $role, User $user, int $tenantId, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($role, $user, $tenantId, $actorUserId, $correlationId): void {
            $assignment = AuthorizationRoleAssignment::query()
                ->where('idRole', $role->id)->where('idUser', $user->id)->where('idTenant', $tenantId)->lockForUpdate()->firstOrFail();
            $this->ensureRemovalKeepsAdministrator($role, $tenantId, $assignment->id);
            $before = ['state' => $assignment->state];
            $assignment->update(['state' => AuthorizationAssignmentState::Inactive->value]);
            $this->audit->record(
                'authorization.role_assignment.deactivated',
                'authorization.role_assignment',
                $assignment->id,
                actorUserId: $actorUserId,
                tenantId: $tenantId,
                before: $before,
                after: ['state' => $assignment->state],
                correlationId: $correlationId,
            );
            $this->policyVersions->invalidate(AuthorizationScope::Tenant, $tenantId);
        });
    }

    public function remove(AuthorizationRole $role, User $user, int $tenantId, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($role, $user, $tenantId, $actorUserId, $correlationId): void {
            $assignment = AuthorizationRoleAssignment::query()
                ->where('idRole', $role->id)->where('idUser', $user->id)->where('idTenant', $tenantId)->lockForUpdate()->firstOrFail();
            $this->ensureRemovalKeepsAdministrator($role, $tenantId, $assignment->id);
            $before = ['idRole' => $assignment->idRole, 'idUser' => $assignment->idUser, 'state' => $assignment->state];
            $assignment->delete();
            $this->audit->record(
                'authorization.role_assignment.removed',
                'authorization.role_assignment',
                $assignment->id,
                actorUserId: $actorUserId,
                tenantId: $tenantId,
                before: $before,
                correlationId: $correlationId,
            );
            $this->policyVersions->invalidate(AuthorizationScope::Tenant, $tenantId);
        });
    }

    private function administratorRole(): AuthorizationRole
    {
        return AuthorizationRole::query()->where('key', 'tenant.administrator')->where('scope', AuthorizationScope::Tenant->value)->lockForUpdate()->firstOrFail();
    }

    private function ensureRemovalKeepsAdministrator(AuthorizationRole $role, int $tenantId, int $excludedAssignmentId): void
    {
        if ($role->key !== 'tenant.administrator') {
            return;
        }

        $this->administrators->ensureAnotherActiveDirectAdministrator($tenantId, excludedAssignmentId: $excludedAssignmentId);
    }
}
