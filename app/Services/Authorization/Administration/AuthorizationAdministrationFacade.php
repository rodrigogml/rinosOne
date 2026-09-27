<?php

namespace App\Services\Authorization\Administration;

use App\Domain\Authorization\AuthorizationRoleType;
use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationAuditEvent;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRestriction;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\AuthorizationGroupRoleAssignmentService;
use App\Services\Authorization\AuthorizationGroupService;
use App\Services\Authorization\AuthorizationRestrictionService;
use App\Services\Authorization\AuthorizationRoleAssignmentService;
use App\Services\Authorization\AuthorizationRolePermissionService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Tenant\TenantMembershipService;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationAdministrationFacade
{
    public function __construct(private readonly AuthorizationAdministrationAuthorizer $authorizer, private readonly AuthorizationAuditLogger $audit, private readonly AuthorizationGroupService $groups, private readonly AuthorizationGroupRoleAssignmentService $groupRoles, private readonly AuthorizationRoleAssignmentService $roles, private readonly AuthorizationRolePermissionService $rolePermissions, private readonly AuthorizationRestrictionService $restrictions, private readonly TenantMembershipService $memberships, private readonly EffectiveAccessQuery $effectiveAccess, private readonly AuthorizationService $authorization, private readonly AuthorizationAuditQuery $auditEvents) {}

    /**
     * Verifies management access before an HTTP adapter resolves an administrative target.
     */
    public function assertCanManage(User $actor, int $tenantId): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
    }

    /** Verifies read access before an HTTP adapter resolves an administrative subject. */
    public function assertCanRead(User $actor, int $tenantId): void
    {
        $this->authorizer->assertCanRead($actor, $tenantId);
    }

    /** @return array<string, mixed> */
    public function effectiveAccess(User $actor, int $tenantId, User $subject): array
    {
        $this->authorizer->assertCanRead($actor, $tenantId);
        $access = $this->effectiveAccess->forTenantMember($subject, $tenantId);
        if ($access['membership'] === null) {
            throw new LogicException('The authorization subject is outside the administrative context.');
        }

        return $access;
    }

    /** @return array{allowed: bool, reasonCode: string, factors: array<string, mixed>} */
    public function explain(User $actor, int $tenantId, User $subject, string $permissionKey): array
    {
        $factors = $this->effectiveAccess($actor, $tenantId, $subject);
        $decision = $this->authorization->check($subject, $permissionKey, AuthorizationScope::Tenant, $tenantId);

        return ['allowed' => $decision->allowed, 'reasonCode' => $decision->reasonCode, 'factors' => $factors];
    }

    /** @return LengthAwarePaginator<int, AuthorizationAuditEvent> */
    public function auditEvents(User $actor, int $tenantId, ?string $operation, ?string $targetType, ?int $targetId, int $perPage): LengthAwarePaginator
    {
        $this->authorizer->assertCanRead($actor, $tenantId);

        return $this->auditEvents->forTenant($tenantId, $operation, $targetType, $targetId, $perPage);
    }

    public function createTenantRole(User $actor, int $tenantId, string $key, string $displayName, string $description, ?string $correlationId = null): AuthorizationRole
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        if (! preg_match('/^tenant\\.[a-z0-9.-]+$/', $key) || trim($displayName) === '') {
            throw new LogicException('The tenant authorization role is invalid.');
        }

        return DB::transaction(function () use ($actor, $tenantId, $key, $displayName, $description, $correlationId): AuthorizationRole {
            $role = AuthorizationRole::query()->create(['idTenant' => $tenantId, 'key' => $key, 'displayName' => trim($displayName), 'description' => trim($description), 'scope' => AuthorizationScope::Tenant->value, 'type' => AuthorizationRoleType::Custom->value, 'systemManaged' => false, 'active' => true]);
            $this->audit->record('authorization.role.created', 'authorization.role', $role->id, actorUserId: $actor->id, tenantId: $tenantId, after: ['key' => $role->key], correlationId: $correlationId);

            return $role;
        });
    }

    public function createTenantGroup(User $actor, int $tenantId, string $displayName, ?string $correlationId = null): AuthorizationGroup
    {
        $this->authorizer->assertCanManage($actor, $tenantId);

        return $this->groups->create($displayName, AuthorizationScope::Tenant, $tenantId, $actor->id, $correlationId);
    }

    public function grantPermission(User $actor, int $tenantId, AuthorizationRole $role, AuthorizationPermission $permission): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertRole($role, $tenantId);
        $this->rolePermissions->grant($role, $permission, $actor->id);
    }

    public function assignRole(User $actor, int $tenantId, AuthorizationRole $role, User $subject, ?string $correlationId = null): AuthorizationRoleAssignment
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertAssignableRole($role, $tenantId);

        return $this->roles->assignTenantRole($role, $subject, $tenantId, $actor->id, $correlationId);
    }

    public function addGroupMember(User $actor, int $tenantId, AuthorizationGroup $group, User $subject, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertGroup($group, $tenantId);
        $this->groups->addUser($group, $subject, $actor->id, $correlationId);
    }

    public function grantRoleToGroup(User $actor, int $tenantId, AuthorizationRole $role, AuthorizationGroup $group, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertRole($role, $tenantId);
        $this->assertGroup($group, $tenantId);
        $this->groupRoles->grant($role, $group, $actor->id, $correlationId);
    }

    public function createRestriction(User $actor, int $tenantId, AuthorizationPermission $permission, ?User $user, ?AuthorizationGroup $group, ?DateTimeInterface $startsAt = null, ?DateTimeInterface $endsAt = null, ?string $correlationId = null): AuthorizationRestriction
    {
        $this->authorizer->assertCanManage($actor, $tenantId);

        return $this->restrictions->create($permission, $user, $group, AuthorizationScope::Tenant, $tenantId, $startsAt, $endsAt, $actor->id, $correlationId);
    }

    public function deactivateMembership(User $actor, int $tenantId, TenantMembership $membership, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        if ($membership->idTenant !== $tenantId) {
            throw new LogicException('The tenant membership is outside the administrative context.');
        } $this->memberships->deactivate($membership, $actor->id, $correlationId);
    }

    public function removeRoleAssignment(User $actor, int $tenantId, AuthorizationRole $role, User $subject, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertRoleAssignmentRole($role, $tenantId);
        try {
            $this->roles->remove($role, $subject, $tenantId, $actor->id, $correlationId);
        } catch (LogicException $exception) {
            $this->audit->record('authorization.administration.rejected', 'authorization.role_assignment', $role->id, actorUserId: $actor->id, tenantId: $tenantId, after: ['reason' => 'INVARIANT_REJECTED'], correlationId: $correlationId);
            throw $exception;
        }
    }

    public function removeGroupMember(User $actor, int $tenantId, AuthorizationGroup $group, User $subject, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertGroup($group, $tenantId);
        $this->groups->removeUser($group, $subject, $actor->id, $correlationId);
    }

    public function removeRoleFromGroup(User $actor, int $tenantId, AuthorizationRole $role, AuthorizationGroup $group, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertRoleAssignmentRole($role, $tenantId);
        $this->assertGroup($group, $tenantId);
        $this->groupRoles->remove($role, $group, $actor->id, $correlationId);
    }

    public function deactivateGroup(User $actor, int $tenantId, AuthorizationGroup $group, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        $this->assertGroup($group, $tenantId);
        $this->groups->deactivate($group, $actor->id, $correlationId);
    }

    public function deactivateRestriction(User $actor, int $tenantId, AuthorizationRestriction $restriction, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        if ($restriction->idTenant !== $tenantId) {
            throw new LogicException('The authorization restriction is outside the administrative context.');
        }
        $this->restrictions->deactivate($restriction, $actor->id, $correlationId);
    }

    public function removeRestriction(User $actor, int $tenantId, AuthorizationRestriction $restriction, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        if ($restriction->idTenant !== $tenantId) {
            throw new LogicException('The authorization restriction is outside the administrative context.');
        }
        $this->restrictions->remove($restriction, $actor->id, $correlationId);
    }

    public function removeMembership(User $actor, int $tenantId, TenantMembership $membership, ?string $correlationId = null): void
    {
        $this->authorizer->assertCanManage($actor, $tenantId);
        if ($membership->idTenant !== $tenantId) {
            throw new LogicException('The tenant membership is outside the administrative context.');
        }
        try {
            $this->memberships->remove($membership, $actor->id, $correlationId);
        } catch (LogicException $exception) {
            $this->audit->record('authorization.administration.rejected', 'tenant.membership', $membership->id, actorUserId: $actor->id, tenantId: $tenantId, after: ['reason' => 'INVARIANT_REJECTED'], correlationId: $correlationId);
            throw $exception;
        }
    }

    private function assertRole(AuthorizationRole $role, int $tenantId): void
    {
        if ($role->scope !== AuthorizationScope::Tenant->value || $role->systemManaged || $role->idTenant !== $tenantId) {
            throw new LogicException('The authorization role is outside the administrative context.');
        }
    }

    private function assertRoleAssignmentRole(AuthorizationRole $role, int $tenantId): void
    {
        if ($role->scope !== AuthorizationScope::Tenant->value || ($role->idTenant !== null && $role->idTenant !== $tenantId)) {
            throw new LogicException('The authorization role is outside the administrative context.');
        }
    }

    private function assertAssignableRole(AuthorizationRole $role, int $tenantId): void
    {
        if ($role->scope !== AuthorizationScope::Tenant->value || ($role->idTenant !== null && $role->idTenant !== $tenantId)) {
            throw new LogicException('The authorization role is outside the administrative context.');
        }
    }

    private function assertGroup(AuthorizationGroup $group, int $tenantId): void
    {
        if ($group->scope !== AuthorizationScope::Tenant->value || $group->idTenant !== $tenantId) {
            throw new LogicException('The authorization group is outside the administrative context.');
        }
    }
}
