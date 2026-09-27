<?php

namespace App\Services\Authorization\Resource;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\Tenant\TenantMembershipState;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationResourceRelation;
use App\Models\AuthorizationResourceType;
use App\Models\User;
use App\Services\Authorization\AuthorizationAuditLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationResourceRelationService
{
    public function __construct(
        private readonly AuthorizationResourceRegistry $registry,
        private readonly AuthorizationResourceTypeCatalog $types,
        private readonly AuthorizationAuditLogger $audit,
    ) {}

    public function create(
        ResourceReference $resource,
        string $relationKey,
        ?User $user = null,
        ?AuthorizationGroup $group = null,
        ?int $actorUserId = null,
        ?string $correlationId = null,
    ): AuthorizationResourceRelation {
        return DB::transaction(function () use ($resource, $relationKey, $user, $group, $actorUserId, $correlationId): AuthorizationResourceRelation {
            $this->assertSubject($resource, $user, $group);
            $adapter = $this->registry->adapterFor($resource);
            if (! in_array($relationKey, $adapter->supportedRelations(), true)) {
                throw new LogicException('The resource relation is not supported by this resource type.');
            }

            $type = $this->types->resolve($resource);
            $relation = AuthorizationResourceRelation::query()->firstOrNew([
                'idResourceType' => $type->id,
                'resourceId' => $resource->id,
                'idUser' => $user?->id,
                'idGroup' => $group?->id,
                'idTenant' => $resource->tenantId,
                'relationKey' => $relationKey,
                'scope' => $resource->scope->value,
            ]);
            $before = $relation->exists ? ['active' => $relation->active] : null;
            $relation->active = true;
            $relation->save();

            if ($relation->wasRecentlyCreated || $relation->wasChanged('active')) {
                $this->audit->record(
                    'authorization.resource_relation.activated',
                    'authorization.resource_relation',
                    $relation->id,
                    actorUserId: $actorUserId,
                    tenantId: $resource->tenantId,
                    before: $before,
                    after: $this->snapshot($relation),
                    correlationId: $correlationId,
                );
            }

            return $relation;
        });
    }

    public function deactivate(AuthorizationResourceRelation $relation, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($relation, $actorUserId, $correlationId): void {
            $relation = AuthorizationResourceRelation::query()->lockForUpdate()->findOrFail($relation->id);
            if (! $relation->active) {
                return;
            }
            $before = $this->snapshot($relation);
            $relation->forceFill(['active' => false])->save();
            $this->audit->record('authorization.resource_relation.deactivated', 'authorization.resource_relation', $relation->id, actorUserId: $actorUserId, tenantId: $relation->idTenant, before: $before, after: $this->snapshot($relation), correlationId: $correlationId);
        });
    }

    public function updateRelationKey(AuthorizationResourceRelation $relation, string $relationKey, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($relation, $relationKey, $actorUserId, $correlationId): void {
            $relation = AuthorizationResourceRelation::query()->lockForUpdate()->findOrFail($relation->id);
            $type = AuthorizationResourceType::query()->findOrFail($relation->idResourceType);
            $resource = new ResourceReference($type->key, $relation->resourceId, AuthorizationScope::from($relation->scope), $relation->idTenant);
            $adapter = $this->registry->adapterFor($resource);
            if (! in_array($relationKey, $adapter->supportedRelations(), true)) {
                throw new LogicException('The resource relation is not supported by this resource type.');
            }
            if ($relation->relationKey === $relationKey) {
                return;
            }
            $before = $this->snapshot($relation);
            $relation->forceFill(['relationKey' => $relationKey])->save();
            $this->audit->record('authorization.resource_relation.updated', 'authorization.resource_relation', $relation->id, actorUserId: $actorUserId, tenantId: $relation->idTenant, before: $before, after: $this->snapshot($relation), correlationId: $correlationId);
        });
    }

    public function remove(AuthorizationResourceRelation $relation, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($relation, $actorUserId, $correlationId): void {
            $relation = AuthorizationResourceRelation::query()->lockForUpdate()->findOrFail($relation->id);
            $before = $this->snapshot($relation);
            $relationId = $relation->id;
            $tenantId = $relation->idTenant;
            $relation->delete();
            $this->audit->record('authorization.resource_relation.removed', 'authorization.resource_relation', $relationId, actorUserId: $actorUserId, tenantId: $tenantId, before: $before, correlationId: $correlationId);
        });
    }

    private function assertSubject(ResourceReference $resource, ?User $user, ?AuthorizationGroup $group): void
    {
        if (($user === null) === ($group === null)) {
            throw new LogicException('A resource relation requires exactly one user or group subject.');
        }
        if ($group !== null && ($group->scope !== $resource->scope->value || $group->idTenant !== $resource->tenantId)) {
            throw new LogicException('The resource relation group does not belong to the resource context.');
        }
        if ($resource->scope === AuthorizationScope::Tenant && $user !== null
            && ! DB::table('tenant_membership')->where('idTenant', $resource->tenantId)->where('idUser', $user->id)->where('state', TenantMembershipState::Active->value)->exists()) {
            throw new LogicException('A tenant resource relation requires an active tenant membership.');
        }
    }

    /** @return array<string, int|string|bool|null> */
    private function snapshot(AuthorizationResourceRelation $relation): array
    {
        return [
            'idResourceType' => $relation->idResourceType,
            'resourceId' => $relation->resourceId,
            'idUser' => $relation->idUser,
            'idGroup' => $relation->idGroup,
            'idTenant' => $relation->idTenant,
            'relationKey' => $relation->relationKey,
            'scope' => $relation->scope,
            'active' => $relation->active,
        ];
    }
}
