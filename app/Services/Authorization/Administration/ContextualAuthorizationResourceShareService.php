<?php

namespace App\Services\Authorization\Administration;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationResourceRelation;
use App\Models\AuthorizationResourceType;
use App\Models\User;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationResourceShareDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationSubjectDto;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Adapts registered workspace-folder relations to the contextual sharing contract.
 *
 * The service never assigns an individual item owner: the workspace context remains
 * the sole responsibility boundary for every relation it reads or changes.
 */
class ContextualAuthorizationResourceShareService
{
    public function __construct(
        private readonly AuthorizationResourceRegistry $resources,
        private readonly AuthorizationResourceRelationService $relations,
    ) {}

    /** @return list<AuthorizationAdministrationResourceShareDto> */
    public function shares(AuthorizationAdministrationContext $context, int $resourceId): array
    {
        $resource = $this->folder($context, $resourceId);
        $adapter = $this->resources->adapterFor($resource);
        $this->resources->assertAvailable($resource);
        $resourceIds = $adapter->inheritedResourceIds($resource);
        $typeId = AuthorizationResourceType::query()->where('key', $resource->type)->value('id');
        if ($typeId === null) {
            return [];
        }

        return AuthorizationResourceRelation::query()
            ->leftJoin('user', 'user.id', '=', 'auth_resource_relation.idUser')
            ->leftJoin('auth_group', 'auth_group.id', '=', 'auth_resource_relation.idGroup')
            ->where('auth_resource_relation.idResourceType', $typeId)
            ->whereIn('auth_resource_relation.resourceId', $resourceIds)
            ->where('auth_resource_relation.scope', $context->scope->value)
            ->when($context->scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_resource_relation.idTenant', $context->tenantId), fn ($query) => $query->whereNull('auth_resource_relation.idTenant'))
            ->where('auth_resource_relation.active', true)
            ->orderBy('auth_resource_relation.resourceId')
            ->orderBy('auth_resource_relation.id')
            ->get(['auth_resource_relation.*', 'user.displayName as userDisplayName', 'auth_group.displayName as groupDisplayName'])
            ->map(fn (AuthorizationResourceRelation $relation): AuthorizationAdministrationResourceShareDto => $this->share($relation, $resourceId))
            ->all();
    }

    public function create(User $actor, AuthorizationAdministrationContext $context, int $resourceId, int $subjectId, string $relationKey): AuthorizationAdministrationResourceShareDto
    {
        $resource = $this->folder($context, $resourceId);
        $this->resources->assertAvailable($resource);
        $relation = $this->relations->create($resource, $relationKey, User::query()->findOrFail($subjectId), actorUserId: $actor->id);

        return $this->share($relation->loadMissing([]), $resourceId);
    }

    public function update(User $actor, AuthorizationAdministrationContext $context, int $resourceId, int $shareId, string $relationKey): AuthorizationAdministrationResourceShareDto
    {
        $relation = $this->directRelation($context, $resourceId, $shareId);
        $this->relations->updateRelationKey($relation, $relationKey, $actor->id);

        return $this->share($relation->fresh(), $resourceId);
    }

    public function revoke(User $actor, AuthorizationAdministrationContext $context, int $resourceId, int $shareId): void
    {
        $this->relations->deactivate($this->directRelation($context, $resourceId, $shareId), $actor->id);
    }

    private function directRelation(AuthorizationAdministrationContext $context, int $resourceId, int $shareId): AuthorizationResourceRelation
    {
        $resource = $this->folder($context, $resourceId);
        $this->resources->assertAvailable($resource);
        $typeId = AuthorizationResourceType::query()->where('key', $resource->type)->value('id');
        $relation = AuthorizationResourceRelation::query()
            ->whereKey($shareId)
            ->where('idResourceType', $typeId)
            ->where('resourceId', $resourceId)
            ->where('scope', $context->scope->value)
            ->when($context->scope === AuthorizationScope::Tenant, fn ($query) => $query->where('idTenant', $context->tenantId), fn ($query) => $query->whereNull('idTenant'))
            ->where('active', true)
            ->first();
        if ($relation === null) {
            throw new LogicException('The authorization resource relation is not directly available.');
        }

        return $relation;
    }

    private function folder(AuthorizationAdministrationContext $context, int $resourceId): ResourceReference
    {
        if ($resourceId < 1 || ! in_array($context->scope, [AuthorizationScope::Personal, AuthorizationScope::Tenant], true)) {
            throw new LogicException('The authorization resource is not available.');
        }

        return new ResourceReference($context->scope === AuthorizationScope::Personal ? 'personal.folder' : 'tenant.folder', $resourceId, $context->scope, $context->tenantId);
    }

    private function share(AuthorizationResourceRelation $relation, int $requestedResourceId): AuthorizationAdministrationResourceShareDto
    {
        $subject = $relation->idUser === null
            ? new AuthorizationAdministrationSubjectDto((int) $relation->idGroup, 'GROUP', (string) DB::table('auth_group')->where('id', $relation->idGroup)->value('displayName'))
            : new AuthorizationAdministrationSubjectDto((int) $relation->idUser, 'USER', (string) DB::table('user')->where('id', $relation->idUser)->value('displayName'));
        $inherited = $relation->resourceId === $requestedResourceId ? null : ['resourceType' => 'FOLDER', 'resourceId' => (int) $relation->resourceId];

        return new AuthorizationAdministrationResourceShareDto((int) $relation->id, 'FOLDER', (int) $requestedResourceId, $subject, $relation->relationKey, $inherited === null ? 'DIRECT' : 'INHERITED', $inherited);
    }
}
