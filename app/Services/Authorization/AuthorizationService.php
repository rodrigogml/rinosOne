<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationAssignmentState;
use App\Domain\Authorization\AuthorizationDecision;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class AuthorizationService
{
    public function __construct(private readonly AuthorizationResourceRegistry $resources) {}

    public function check(User $principal, string $permissionKey, AuthorizationScope $scope, ?int $tenantId = null, ?ResourceReference $resource = null): AuthorizationDecision
    {
        if ($scope === AuthorizationScope::Tenant) {
            if ($tenantId === null || $tenantId < 1) {
                return new AuthorizationDecision(false, 'TENANT_CONTEXT_REQUIRED');
            }
            if (! Tenant::query()->whereKey($tenantId)->where('state', TenantState::Active)->exists()) {
                return new AuthorizationDecision(false, 'TENANT_NOT_AVAILABLE');
            }
            if (! TenantMembership::query()->where('idTenant', $tenantId)->where('idUser', $principal->id)->where('state', TenantMembershipState::Active)->exists()) {
                return new AuthorizationDecision(false, 'TENANT_MEMBERSHIP_REQUIRED');
            }
        } elseif ($tenantId !== null) {
            return new AuthorizationDecision(false, 'TENANT_CONTEXT_NOT_ALLOWED');
        }

        if ($this->restrictionApplies($principal->id, $permissionKey, $scope, $tenantId)) {
            return new AuthorizationDecision(false, 'RESTRICTION_APPLIES');
        }

        if ($resource !== null) {
            if ($resource->scope !== $scope || $resource->tenantId !== $tenantId) {
                return new AuthorizationDecision(false, 'RESOURCE_CONTEXT_INVALID');
            }
            try {
                $adapter = $this->resources->adapterFor($resource);
                if (! $adapter->exists($resource)) {
                    return new AuthorizationDecision(false, 'RESOURCE_NOT_AVAILABLE');
                }
            } catch (\LogicException) {
                return new AuthorizationDecision(false, 'RESOURCE_NOT_AVAILABLE');
            }

            if (in_array($permissionKey, $adapter->supportedActions(), true)) {
                if ($adapter->isWorkspacePrincipal($resource, $principal->id)) {
                    return new AuthorizationDecision(true, 'WORKSPACE_PRINCIPAL_APPLIES');
                }
                $relationKey = str_ends_with($permissionKey, '.edit') ? 'EDIT' : 'READ';
                $resourceIds = $adapter->inheritedResourceIds($resource);
                $directRelationApplies = DB::table('auth_resource_relation')
                    ->join('auth_resource_type', 'auth_resource_type.id', '=', 'auth_resource_relation.idResourceType')
                    ->where('auth_resource_type.key', $resource->type)
                    ->where('auth_resource_type.active', true)
                    ->whereIn('auth_resource_relation.resourceId', $resourceIds)
                    ->where('auth_resource_relation.idUser', $principal->id)
                    ->where('auth_resource_relation.scope', $scope->value)
                    ->where('auth_resource_relation.relationKey', $relationKey)
                    ->where('auth_resource_relation.active', true)
                    ->when($scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_resource_relation.idTenant', $tenantId), fn ($query) => $query->whereNull('auth_resource_relation.idTenant'))
                    ->exists();
                $groupRelationApplies = $this->groupResourceRelationApplies(
                    $principal->id,
                    $resource->type,
                    $resourceIds,
                    $relationKey,
                    $scope,
                    $tenantId,
                );
                $allowed = $directRelationApplies || $groupRelationApplies;

                return new AuthorizationDecision($allowed, $allowed ? 'RESOURCE_RELATION_APPLIES' : 'RESOURCE_RELATION_REQUIRED');
            }
        }

        $directGrantApplies = DB::table('auth_role_assignment')
            ->join('auth_role', 'auth_role.id', '=', 'auth_role_assignment.idRole')
            ->join('auth_role_permission', 'auth_role_permission.idRole', '=', 'auth_role.id')
            ->join('auth_permission', 'auth_permission.id', '=', 'auth_role_permission.idPermission')
            ->where('auth_role_assignment.idUser', $principal->id)
            ->where('auth_role_assignment.state', 'ACTIVE')
            ->where('auth_role.active', true)
            ->where('auth_role.scope', $scope->value)
            ->where('auth_permission.active', true)
            ->where('auth_permission.key', $permissionKey)
            ->where('auth_permission.scope', $scope->value)
            ->when($scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_role_assignment.idTenant', $tenantId), fn ($query) => $query->whereNull('auth_role_assignment.idTenant'))
            ->exists();

        $groupGrantApplies = $this->groupGrantApplies($principal->id, $permissionKey, $scope, $tenantId);

        $allowed = $directGrantApplies || $groupGrantApplies;

        return new AuthorizationDecision($allowed, $allowed ? 'GRANT_APPLIES' : 'NO_APPLICABLE_GRANT');
    }

    private function groupGrantApplies(int $userId, string $permissionKey, AuthorizationScope $scope, ?int $tenantId): bool
    {
        $isTenantScope = $scope === AuthorizationScope::Tenant;
        $groupTenantPredicate = $isTenantScope ? 'AND direct_group.idTenant = ?' : 'AND direct_group.idTenant IS NULL';
        $parentTenantPredicate = $isTenantScope ? 'AND parent_group.idTenant = ?' : 'AND parent_group.idTenant IS NULL';
        $assignmentTenantPredicate = $isTenantScope ? 'AND auth_group_role_assignment.idTenant = ?' : 'AND auth_group_role_assignment.idTenant IS NULL';
        $bindings = [$userId, $scope->value];

        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }

        $bindings[] = $scope->value;
        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }

        $bindings[] = AuthorizationAssignmentState::Active->value;
        $bindings[] = $scope->value;
        $bindings[] = $permissionKey;
        $bindings[] = $scope->value;
        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }

        $result = DB::selectOne(
            "WITH RECURSIVE eligible_group(id) AS (
                SELECT direct_group.id
                FROM auth_group_user
                INNER JOIN auth_group direct_group ON direct_group.id = auth_group_user.idGroup
                WHERE auth_group_user.idUser = ?
                    AND direct_group.active = 1
                    AND direct_group.scope = ?
                    {$groupTenantPredicate}
                UNION
                SELECT relation.idParentGroup
                FROM auth_group_group relation
                INNER JOIN eligible_group ON eligible_group.id = relation.idChildGroup
                INNER JOIN auth_group parent_group ON parent_group.id = relation.idParentGroup
                WHERE parent_group.active = 1
                    AND parent_group.scope = ?
                    {$parentTenantPredicate}
            )
            SELECT EXISTS(
                SELECT 1
                FROM eligible_group
                INNER JOIN auth_group_role_assignment ON auth_group_role_assignment.idGroup = eligible_group.id
                INNER JOIN auth_role ON auth_role.id = auth_group_role_assignment.idRole
                INNER JOIN auth_role_permission ON auth_role_permission.idRole = auth_role.id
                INNER JOIN auth_permission ON auth_permission.id = auth_role_permission.idPermission
                WHERE auth_group_role_assignment.state = ?
                    AND auth_role.active = 1
                    AND auth_role.scope = ?
                    AND auth_permission.active = 1
                    AND auth_permission.key = ?
                    AND auth_permission.scope = ?
                    {$assignmentTenantPredicate}
            ) AS allowed",
            $bindings,
        );

        return (bool) $result->allowed;
    }

    /**
     * Applies resource relations granted to a direct group or any active ancestor group.
     *
     * @param  list<int>  $resourceIds
     */
    private function groupResourceRelationApplies(int $userId, string $resourceType, array $resourceIds, string $relationKey, AuthorizationScope $scope, ?int $tenantId): bool
    {
        if ($resourceIds === []) {
            return false;
        }

        $isTenantScope = $scope === AuthorizationScope::Tenant;
        $groupTenantPredicate = $isTenantScope ? 'AND direct_group.idTenant = ?' : 'AND direct_group.idTenant IS NULL';
        $parentTenantPredicate = $isTenantScope ? 'AND parent_group.idTenant = ?' : 'AND parent_group.idTenant IS NULL';
        $relationTenantPredicate = $isTenantScope ? 'AND auth_resource_relation.idTenant = ?' : 'AND auth_resource_relation.idTenant IS NULL';
        $resourcePlaceholders = implode(', ', array_fill(0, count($resourceIds), '?'));
        $bindings = [$userId, $scope->value];

        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }
        $bindings[] = $scope->value;
        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }
        $bindings[] = $resourceType;
        array_push($bindings, ...$resourceIds);
        $bindings[] = $scope->value;
        $bindings[] = $relationKey;
        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }

        $result = DB::selectOne(
            "WITH RECURSIVE eligible_group(id) AS (
                SELECT direct_group.id
                FROM auth_group_user
                INNER JOIN auth_group direct_group ON direct_group.id = auth_group_user.idGroup
                WHERE auth_group_user.idUser = ?
                    AND direct_group.active = 1
                    AND direct_group.scope = ?
                    {$groupTenantPredicate}
                UNION
                SELECT relation.idParentGroup
                FROM auth_group_group relation
                INNER JOIN eligible_group ON eligible_group.id = relation.idChildGroup
                INNER JOIN auth_group parent_group ON parent_group.id = relation.idParentGroup
                WHERE parent_group.active = 1
                    AND parent_group.scope = ?
                    {$parentTenantPredicate}
            )
            SELECT EXISTS(
                SELECT 1
                FROM eligible_group
                INNER JOIN auth_resource_relation ON auth_resource_relation.idGroup = eligible_group.id
                INNER JOIN auth_resource_type ON auth_resource_type.id = auth_resource_relation.idResourceType
                WHERE auth_resource_type.key = ?
                    AND auth_resource_type.active = 1
                    AND auth_resource_relation.resourceId IN ({$resourcePlaceholders})
                    AND auth_resource_relation.scope = ?
                    AND auth_resource_relation.relationKey = ?
                    AND auth_resource_relation.active = 1
                    {$relationTenantPredicate}
            ) AS allowed",
            $bindings,
        );

        return (bool) $result->allowed;
    }

    private function restrictionApplies(int $userId, string $permissionKey, AuthorizationScope $scope, ?int $tenantId): bool
    {
        $now = now();
        $directRestrictionApplies = DB::table('auth_restriction')
            ->join('auth_permission', 'auth_permission.id', '=', 'auth_restriction.idPermission')
            ->where('auth_restriction.idUser', $userId)
            ->where('auth_restriction.active', true)
            ->where('auth_restriction.scope', $scope->value)
            ->where('auth_permission.active', true)
            ->where('auth_permission.key', $permissionKey)
            ->where('auth_permission.scope', $scope->value)
            ->where(function ($query) use ($now): void {
                $query->whereNull('auth_restriction.startsAt')->orWhere('auth_restriction.startsAt', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('auth_restriction.endsAt')->orWhere('auth_restriction.endsAt', '>', $now);
            })
            ->when($scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_restriction.idTenant', $tenantId), fn ($query) => $query->whereNull('auth_restriction.idTenant'))
            ->exists();

        return $directRestrictionApplies || $this->groupRestrictionApplies($userId, $permissionKey, $scope, $tenantId, $now);
    }

    private function groupRestrictionApplies(int $userId, string $permissionKey, AuthorizationScope $scope, ?int $tenantId, DateTimeInterface $now): bool
    {
        $isTenantScope = $scope === AuthorizationScope::Tenant;
        $groupTenantPredicate = $isTenantScope ? 'AND direct_group.idTenant = ?' : 'AND direct_group.idTenant IS NULL';
        $parentTenantPredicate = $isTenantScope ? 'AND parent_group.idTenant = ?' : 'AND parent_group.idTenant IS NULL';
        $restrictionTenantPredicate = $isTenantScope ? 'AND auth_restriction.idTenant = ?' : 'AND auth_restriction.idTenant IS NULL';
        $bindings = [$userId, $scope->value];

        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }

        $bindings[] = $scope->value;
        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }

        $bindings[] = $scope->value;
        $bindings[] = $permissionKey;
        $bindings[] = $scope->value;
        $bindings[] = $now;
        $bindings[] = $now;
        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }

        $result = DB::selectOne(
            "WITH RECURSIVE eligible_group(id) AS (
                SELECT direct_group.id
                FROM auth_group_user
                INNER JOIN auth_group direct_group ON direct_group.id = auth_group_user.idGroup
                WHERE auth_group_user.idUser = ?
                    AND direct_group.active = 1
                    AND direct_group.scope = ?
                    {$groupTenantPredicate}
                UNION
                SELECT relation.idParentGroup
                FROM auth_group_group relation
                INNER JOIN eligible_group ON eligible_group.id = relation.idChildGroup
                INNER JOIN auth_group parent_group ON parent_group.id = relation.idParentGroup
                WHERE parent_group.active = 1
                    AND parent_group.scope = ?
                    {$parentTenantPredicate}
            )
            SELECT EXISTS(
                SELECT 1
                FROM eligible_group
                INNER JOIN auth_restriction ON auth_restriction.idGroup = eligible_group.id
                INNER JOIN auth_permission ON auth_permission.id = auth_restriction.idPermission
                WHERE auth_restriction.active = 1
                    AND auth_restriction.scope = ?
                    AND auth_permission.active = 1
                    AND auth_permission.key = ?
                    AND auth_permission.scope = ?
                    AND (auth_restriction.startsAt IS NULL OR auth_restriction.startsAt <= ?)
                    AND (auth_restriction.endsAt IS NULL OR auth_restriction.endsAt > ?)
                    {$restrictionTenantPredicate}
            ) AS restricted",
            $bindings,
        );

        return (bool) $result->restricted;
    }
}
