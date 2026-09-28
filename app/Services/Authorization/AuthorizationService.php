<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationAssignmentState;
use App\Domain\Authorization\AuthorizationDecision;
use App\Domain\Authorization\AuthorizationEvaluationContext;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Infrastructure\Authorization\Observability\AuthorizationMetrics;
use App\Models\AuthorizationPermission;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Performance\PolicyVersionService;
use App\Services\Authorization\Advanced\AuthorizationPolicyEvaluator;
use App\Services\Authorization\Advanced\AuthorizationPermissionImplicationResolver;
use App\Services\Authorization\Advanced\AuthorizationSeparationEvaluator;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AuthorizationService
{
    public function __construct(
        private readonly AuthorizationResourceRegistry $resources,
        private readonly PolicyVersionService $policyVersions,
        private readonly AuthorizationMetrics $metrics,
        private readonly AuthorizationPolicyEvaluator $policyEvaluator,
        private readonly AuthorizationPermissionImplicationResolver $implicationResolver,
        private readonly AuthorizationSeparationEvaluator $separationEvaluator,
    ) {}

    public function check(User $principal, string $permissionKey, AuthorizationScope $scope, ?int $tenantId = null, ?ResourceReference $resource = null, ?AuthorizationEvaluationContext $context = null): AuthorizationDecision
    {
        $startedAt = hrtime(true);
        $cacheHit = false;
        $decision = null;
        $cacheKey = null;
        $cacheSeconds = 0;
        try {
            try {
                $version = (string) $this->policyVersions->current($scope, $tenantId);
                if ($scope === AuthorizationScope::Tenant) {
                    $version .= ':'.$this->policyVersions->current(AuthorizationScope::Tenant);
                }
                $cacheSeconds = max(0, (int) config('authorization.decisionCacheSeconds', 300));
                if ($cacheSeconds > 0) {
                    $cacheKey = 'authorization:decision:'.hash('sha256', implode('|', [$principal->id, $permissionKey, $scope->value, $tenantId ?? 'none', $resource?->type ?? 'none', $resource?->id ?? 'none', $context?->fingerprint() ?? 'none', $version]));
                }
            } catch (\Throwable) {
                // Cache and policy-version infrastructure is an accelerator only.
            }

            if ($cacheKey !== null) {
                try {
                    $cached = Cache::get($cacheKey);
                    if ($cached instanceof AuthorizationDecision) {
                        $cacheHit = true;
                        $decision = $cached;

                        return $decision;
                    }
                } catch (\Throwable) {
                    $cacheKey = null;
                }
            }

            $decision = $this->resolve($principal, $permissionKey, $scope, $tenantId, $resource);
            if ($decision->allowed && $this->separationEvaluator->violates($permissionKey, $scope, $tenantId, fn (string $otherKey): bool => $this->resolve($principal, $otherKey, $scope, $tenantId)->allowed)) {
                $decision = new AuthorizationDecision(false, 'SEPARATION_OF_DUTIES_APPLIES');
            }
            if ($decision->allowed && ! $this->policyEvaluator->qualifies($permissionKey, $scope, $tenantId, $resource, $context)) {
                $decision = new AuthorizationDecision(false, 'POLICY_CONDITION_NOT_SATISFIED');
            }
            if ($cacheKey !== null) {
                try {
                    Cache::put($cacheKey, $decision, now()->addSeconds($cacheSeconds));
                } catch (\Throwable) {
                    // Cache infrastructure is an accelerator only.
                }
            }

            return $decision;
        } catch (\Throwable $exception) {
            $this->metrics->recordFailure();
            throw $exception;
        } finally {
            if ($decision !== null) {
                $this->metrics->recordDecision($decision->allowed, (int) ((hrtime(true) - $startedAt) / 1_000_000), $cacheHit);
            }
        }
    }

    /**
     * Resolves a bounded set of authorization requests in submitted order.
     *
     * @param  list<array{permissionKey: string, tenantId?: int, resource?: array{type: string, id: int}}>  $checks
     * @return list<array{allowed: bool, reasonCode: string}>
     */
    public function checkBatch(User $principal, array $checks): array
    {
        $this->metrics->recordBatch(count($checks));
        $permissions = AuthorizationPermission::query()
            ->whereIn('key', collect($checks)->pluck('permissionKey')->unique())
            ->where('active', true)
            ->get()
            ->keyBy('key');

        return array_map(function (array $check) use ($permissions, $principal): array {
            $permission = $permissions->get($check['permissionKey']);
            $scope = $permission === null ? null : AuthorizationScope::tryFrom($permission->scope);
            if ($scope === null) {
                return ['allowed' => false, 'reasonCode' => 'PERMISSION_NOT_AVAILABLE'];
            }

            try {
                $resource = isset($check['resource']) ? new ResourceReference(
                    $check['resource']['type'],
                    $check['resource']['id'],
                    $scope,
                    $check['tenantId'] ?? null,
                ) : null;
            } catch (InvalidArgumentException) {
                return ['allowed' => false, 'reasonCode' => 'RESOURCE_CONTEXT_INVALID'];
            }

            $decision = $this->check($principal, $permission->key, $scope, $check['tenantId'] ?? null, $resource);

            return ['allowed' => $decision->allowed, 'reasonCode' => $decision->reasonCode];
        }, $checks);
    }

    private function resolve(User $principal, string $permissionKey, AuthorizationScope $scope, ?int $tenantId = null, ?ResourceReference $resource = null): AuthorizationDecision
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

        if ($this->restrictionApplies($principal->id, $permissionKey, $scope, $tenantId, $resource)) {
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

        $grantPermissionIds = $this->implicationResolver->grantPermissionIdsFor($permissionKey, $scope);
        $directGrantApplies = DB::table('auth_role_assignment')
            ->join('auth_role', 'auth_role.id', '=', 'auth_role_assignment.idRole')
            ->join('auth_role_permission', 'auth_role_permission.idRole', '=', 'auth_role.id')
            ->join('auth_permission', 'auth_permission.id', '=', 'auth_role_permission.idPermission')
            ->where('auth_role_assignment.idUser', $principal->id)
            ->where('auth_role_assignment.state', 'ACTIVE')
            ->where('auth_role.active', true)
            ->where('auth_role.scope', $scope->value)
            ->where('auth_permission.active', true)
            ->whereIn('auth_permission.id', $grantPermissionIds)
            ->where('auth_permission.scope', $scope->value)
            ->when($scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_role_assignment.idTenant', $tenantId), fn ($query) => $query->whereNull('auth_role_assignment.idTenant'))
            ->exists();

        $groupGrantApplies = $this->groupGrantApplies($principal->id, $grantPermissionIds, $scope, $tenantId);
        $delegationGrantApplies = $this->delegationGrantApplies($principal->id, $grantPermissionIds, $scope, $tenantId);
        $temporaryRequestGrantApplies = $this->temporaryRequestGrantApplies($principal->id, $grantPermissionIds, $scope, $tenantId);

        $allowed = $directGrantApplies || $groupGrantApplies || $delegationGrantApplies || $temporaryRequestGrantApplies;

        return new AuthorizationDecision($allowed, $allowed ? 'GRANT_APPLIES' : 'NO_APPLICABLE_GRANT');
    }

    /** @param list<int> $grantPermissionIds */
    private function temporaryRequestGrantApplies(int $userId, array $grantPermissionIds, AuthorizationScope $scope, ?int $tenantId): bool
    {
        return $scope === AuthorizationScope::Tenant && $grantPermissionIds !== [] && DB::table('auth_access_request')
            ->where('idRecipientUser', $userId)->where('idTenant', $tenantId)->where('scope', $scope->value)
            ->where('state', 'APPROVED')->whereIn('idPermission', $grantPermissionIds)
            ->where('startsAt', '<=', now())->where('endsAt', '>', now())->exists();
    }

    /** @param list<int> $grantPermissionIds */
    private function delegationGrantApplies(int $userId, array $grantPermissionIds, AuthorizationScope $scope, ?int $tenantId): bool
    {
        if ($grantPermissionIds === []) {
            return false;
        }

        return DB::table('auth_delegation')
            ->join('auth_role_assignment as origin_assignment', 'origin_assignment.id', '=', 'auth_delegation.originId')
            ->join('auth_role_permission as origin_permission', 'origin_permission.idRole', '=', 'origin_assignment.idRole')
            ->where('auth_delegation.idRecipientUser', $userId)->where('auth_delegation.scope', $scope->value)
            ->where('auth_delegation.originType', 'ROLE_ASSIGNMENT')->where('auth_delegation.state', 'ACTIVE')
            ->where('auth_delegation.startsAt', '<=', now())->where('auth_delegation.endsAt', '>', now())
            ->whereIn('auth_delegation.idPermission', $grantPermissionIds)
            ->whereColumn('origin_permission.idPermission', 'auth_delegation.idPermission')
            ->whereColumn('origin_assignment.idUser', 'auth_delegation.idDelegatorUser')
            ->where('origin_assignment.state', 'ACTIVE')
            ->when($scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_delegation.idTenant', $tenantId)->where('origin_assignment.idTenant', $tenantId), fn ($query) => $query->whereNull('auth_delegation.idTenant')->whereNull('origin_assignment.idTenant'))
            ->exists();
    }

    /** @param list<int> $grantPermissionIds */
    private function groupGrantApplies(int $userId, array $grantPermissionIds, AuthorizationScope $scope, ?int $tenantId): bool
    {
        if ($grantPermissionIds === []) {
            return false;
        }

        $isTenantScope = $scope === AuthorizationScope::Tenant;
        $groupTenantPredicate = $isTenantScope ? 'AND direct_group.idTenant = ?' : 'AND direct_group.idTenant IS NULL';
        $parentTenantPredicate = $isTenantScope ? 'AND parent_group.idTenant = ?' : 'AND parent_group.idTenant IS NULL';
        $assignmentTenantPredicate = $isTenantScope ? 'AND auth_group_role_assignment.idTenant = ?' : 'AND auth_group_role_assignment.idTenant IS NULL';
        $permissionPlaceholders = implode(', ', array_fill(0, count($grantPermissionIds), '?'));
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
        array_push($bindings, ...$grantPermissionIds);
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
                    AND auth_permission.id IN ({$permissionPlaceholders})
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

    private function restrictionApplies(int $userId, string $permissionKey, AuthorizationScope $scope, ?int $tenantId, ?ResourceReference $resource): bool
    {
        $now = now();
        $directRestrictionApplies = DB::table('auth_restriction')
            ->join('auth_permission', 'auth_permission.id', '=', 'auth_restriction.idPermission')
            ->leftJoin('auth_resource_type as restriction_resource_type', 'restriction_resource_type.id', '=', 'auth_restriction.idResourceType')
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
            ->where(function ($query) use ($resource): void {
                if ($resource === null) {
                    $query->whereNull('auth_restriction.idResourceType')->whereNull('auth_restriction.resourceId');

                    return;
                }
                $query->where(function ($qualifier) use ($resource): void {
                    $qualifier->where(function ($global) use ($resource): void {
                        $global->whereNull('auth_restriction.idResourceType')->whereNull('auth_restriction.resourceId');
                    })->orWhere(function ($specific) use ($resource): void {
                        $specific->where('restriction_resource_type.key', $resource->type)->where('auth_restriction.resourceId', $resource->id);
                    });
                });
            })
            ->exists();

        return $directRestrictionApplies || $this->groupRestrictionApplies($userId, $permissionKey, $scope, $tenantId, $now, $resource);
    }

    private function groupRestrictionApplies(int $userId, string $permissionKey, AuthorizationScope $scope, ?int $tenantId, DateTimeInterface $now, ?ResourceReference $resource): bool
    {
        $isTenantScope = $scope === AuthorizationScope::Tenant;
        $groupTenantPredicate = $isTenantScope ? 'AND direct_group.idTenant = ?' : 'AND direct_group.idTenant IS NULL';
        $parentTenantPredicate = $isTenantScope ? 'AND parent_group.idTenant = ?' : 'AND parent_group.idTenant IS NULL';
        $restrictionTenantPredicate = $isTenantScope ? 'AND auth_restriction.idTenant = ?' : 'AND auth_restriction.idTenant IS NULL';
        $restrictionResourcePredicate = $resource === null
            ? 'AND auth_restriction.idResourceType IS NULL AND auth_restriction.resourceId IS NULL'
            : 'AND ((auth_restriction.idResourceType IS NULL AND auth_restriction.resourceId IS NULL) OR (restriction_resource_type.key = ? AND auth_restriction.resourceId = ?))';
        $bindings = [$userId, $scope->value];

        if ($isTenantScope) {
            $bindings[] = $tenantId;
        }
        if ($resource !== null) {
            $bindings[] = $resource->type;
            $bindings[] = $resource->id;
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
                LEFT JOIN auth_resource_type restriction_resource_type ON restriction_resource_type.id = auth_restriction.idResourceType
                WHERE auth_restriction.active = 1
                    AND auth_restriction.scope = ?
                    AND auth_permission.active = 1
                    AND auth_permission.key = ?
                    AND auth_permission.scope = ?
                    AND (auth_restriction.startsAt IS NULL OR auth_restriction.startsAt <= ?)
                    AND (auth_restriction.endsAt IS NULL OR auth_restriction.endsAt > ?)
                    {$restrictionTenantPredicate}
                    {$restrictionResourcePredicate}
            ) AS restricted",
            $bindings,
        );

        return (bool) $result->restricted;
    }
}
