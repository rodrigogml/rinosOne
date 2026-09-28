<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRestriction;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Performance\PolicyVersionService;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationRestrictionService
{
    public function __construct(
        private readonly AuthorizationAuditLogger $audit,
        private readonly PolicyVersionService $policyVersions,
        private readonly AuthorizationResourceRegistry $resources,
    ) {}

    public function create(
        AuthorizationPermission $permission,
        ?User $user,
        ?AuthorizationGroup $group,
        AuthorizationScope $scope,
        ?int $tenantId = null,
        ?DateTimeInterface $startsAt = null,
        ?DateTimeInterface $endsAt = null,
        ?int $actorUserId = null,
        ?string $correlationId = null,
        ?ResourceReference $resource = null,
    ): AuthorizationRestriction {
        return DB::transaction(function () use ($permission, $user, $group, $scope, $tenantId, $startsAt, $endsAt, $actorUserId, $correlationId, $resource): AuthorizationRestriction {
            $permission = AuthorizationPermission::query()->lockForUpdate()->findOrFail($permission->id);
            $group = $group === null ? null : AuthorizationGroup::query()->lockForUpdate()->findOrFail($group->id);
            $resourceTypeId = $this->validate($permission, $user, $group, $scope, $tenantId, $startsAt, $endsAt, $resource);

            $attributes = [
                'idPermission' => $permission->id,
                'idResourceType' => $resourceTypeId,
                'resourceId' => $resource?->id,
                'idUser' => $user?->id,
                'idGroup' => $group?->id,
                'idTenant' => $tenantId,
                'scope' => $scope->value,
                'startsAt' => $startsAt,
                'endsAt' => $endsAt,
            ];
            $restriction = AuthorizationRestriction::query()->firstOrCreate($attributes, ['active' => true]);

            if ($restriction->wasRecentlyCreated) {
                $this->audit->record(
                    'authorization.restriction.created',
                    'authorization.restriction',
                    $restriction->id,
                    actorUserId: $actorUserId,
                    tenantId: $tenantId,
                    after: $this->snapshot($restriction),
                    correlationId: $correlationId,
                );
                $this->policyVersions->invalidate($scope, $tenantId);
            }

            return $restriction;
        });
    }

    public function deactivate(AuthorizationRestriction $restriction, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($restriction, $actorUserId, $correlationId): void {
            $restriction = AuthorizationRestriction::query()->lockForUpdate()->findOrFail($restriction->id);
            if (! $restriction->active) {
                return;
            }

            $before = $this->snapshot($restriction);
            $restriction->update(['active' => false]);
            $this->audit->record(
                'authorization.restriction.deactivated',
                'authorization.restriction',
                $restriction->id,
                actorUserId: $actorUserId,
                tenantId: $restriction->idTenant,
                before: $before,
                after: $this->snapshot($restriction),
                correlationId: $correlationId,
            );
            $this->policyVersions->invalidate(AuthorizationScope::from($restriction->scope), $restriction->idTenant);
        });
    }

    public function activate(AuthorizationRestriction $restriction, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($restriction, $actorUserId, $correlationId): void {
            $restriction = AuthorizationRestriction::query()->lockForUpdate()->findOrFail($restriction->id);
            if ($restriction->active) {
                return;
            }

            $before = $this->snapshot($restriction);
            $restriction->update(['active' => true]);
            $this->audit->record('authorization.restriction.activated', 'authorization.restriction', $restriction->id, actorUserId: $actorUserId, tenantId: $restriction->idTenant, before: $before, after: $this->snapshot($restriction), correlationId: $correlationId);
            $this->policyVersions->invalidate(AuthorizationScope::from($restriction->scope), $restriction->idTenant);
        });
    }

    public function updateValidity(AuthorizationRestriction $restriction, ?DateTimeInterface $startsAt, ?DateTimeInterface $endsAt, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($restriction, $startsAt, $endsAt, $actorUserId, $correlationId): void {
            $restriction = AuthorizationRestriction::query()->lockForUpdate()->findOrFail($restriction->id);
            $this->ensureValidity($startsAt, $endsAt);
            $before = $this->snapshot($restriction);
            $restriction->update(['startsAt' => $startsAt, 'endsAt' => $endsAt]);
            $this->audit->record('authorization.restriction.validity_updated', 'authorization.restriction', $restriction->id, actorUserId: $actorUserId, tenantId: $restriction->idTenant, before: $before, after: $this->snapshot($restriction), correlationId: $correlationId);
            $this->policyVersions->invalidate(AuthorizationScope::from($restriction->scope), $restriction->idTenant);
        });
    }

    public function remove(AuthorizationRestriction $restriction, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($restriction, $actorUserId, $correlationId): void {
            $restriction = AuthorizationRestriction::query()->lockForUpdate()->findOrFail($restriction->id);
            $before = $this->snapshot($restriction);
            $restrictionId = $restriction->id;
            $tenantId = $restriction->idTenant;
            $restriction->delete();
            $this->audit->record('authorization.restriction.removed', 'authorization.restriction', $restrictionId, actorUserId: $actorUserId, tenantId: $tenantId, before: $before, correlationId: $correlationId);
            $this->policyVersions->invalidate(AuthorizationScope::from($restriction->scope), $tenantId);
        });
    }

    private function validate(
        AuthorizationPermission $permission,
        ?User $user,
        ?AuthorizationGroup $group,
        AuthorizationScope $scope,
        ?int $tenantId,
        ?DateTimeInterface $startsAt,
        ?DateTimeInterface $endsAt,
        ?ResourceReference $resource,
    ): ?int {
        if (($user === null) === ($group === null)) {
            throw new LogicException('An authorization restriction requires exactly one subject.');
        }
        if (! $permission->active || $permission->scope !== $scope->value) {
            throw new LogicException('An authorization restriction requires an active permission of the same scope.');
        }
        $this->ensureValidity($startsAt, $endsAt);
        if ($scope !== AuthorizationScope::Tenant) {
            if ($tenantId !== null || ($group !== null && $group->idTenant !== null)) {
                throw new LogicException('Only tenant authorization restrictions can have a tenant context.');
            }

            return $this->validateResource($resource, $scope, $tenantId);
        }
        if ($tenantId === null || ! Tenant::query()->whereKey($tenantId)->where('state', TenantState::Active->value)->lockForUpdate()->exists()) {
            throw new LogicException('A tenant authorization restriction requires an active tenant context.');
        }
        if ($group !== null && ($group->scope !== AuthorizationScope::Tenant->value || $group->idTenant !== $tenantId)) {
            throw new LogicException('A tenant authorization restriction requires a group from the same tenant.');
        }
        if ($user !== null && ! TenantMembership::query()->where('idTenant', $tenantId)->where('idUser', $user->id)->where('state', TenantMembershipState::Active->value)->lockForUpdate()->exists()) {
            throw new LogicException('A tenant authorization restriction requires an active tenant membership.');
        }

        return $this->validateResource($resource, $scope, $tenantId);
    }

    private function validateResource(?ResourceReference $resource, AuthorizationScope $scope, ?int $tenantId): ?int
    {
        if ($resource === null) {
            return null;
        }
        if ($resource->scope !== $scope || $resource->tenantId !== $tenantId) {
            throw new LogicException('An authorization restriction resource must match its scope and tenant context.');
        }
        $this->resources->assertAvailable($resource);
        $resourceTypeId = DB::table('auth_resource_type')->where('key', $resource->type)->where('active', true)->value('id');
        if ($resourceTypeId === null) {
            throw new LogicException('The authorization restriction resource is not registered.');
        }

        return (int) $resourceTypeId;
    }

    private function ensureValidity(?DateTimeInterface $startsAt, ?DateTimeInterface $endsAt): void
    {
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new LogicException('An authorization restriction end must be after its start.');
        }
    }

    private function snapshot(AuthorizationRestriction $restriction): array
    {
        return [
            'idPermission' => $restriction->idPermission,
            'idResourceType' => $restriction->idResourceType,
            'resourceId' => $restriction->resourceId,
            'idUser' => $restriction->idUser,
            'idGroup' => $restriction->idGroup,
            'idTenant' => $restriction->idTenant,
            'scope' => $restriction->scope,
            'startsAt' => $restriction->startsAt?->toIso8601String(),
            'endsAt' => $restriction->endsAt?->toIso8601String(),
            'active' => $restriction->active,
        ];
    }
}
