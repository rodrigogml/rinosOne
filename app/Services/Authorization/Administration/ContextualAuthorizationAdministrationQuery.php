<?php

namespace App\Services\Authorization\Administration;

use App\Domain\Authorization\Administration\AuthorizationAdministrationSubjectNotAvailableException;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Models\AuthorizationAuditEvent;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\User;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationAccessSourceDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationAuditEventDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationCatalogItemDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationSubjectDto;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Produces context-bound read projections without expanding the caller's visibility.
 *
 * Tenant subjects are active members and active service identities in that tenant.
 * Personal and platform contexts intentionally expose only the authenticated user and
 * service identities directly associated with that same user until a separate global
 * subject-directory contract is approved.
 */
class ContextualAuthorizationAdministrationQuery
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly EffectiveAccessQuery $effectiveAccess,
    ) {}

    /** @return LengthAwarePaginator<int, AuthorizationAdministrationSubjectDto> */
    public function subjects(User $actor, AuthorizationAdministrationContext $context, ?string $query, int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        [$page, $perPage] = $this->pagination($page, $perPage);
        $rows = $this->subjectRows($actor, $context, $query, $page, $perPage);

        return $rows->setCollection($rows->getCollection()->map(
            fn (object $row): AuthorizationAdministrationSubjectDto => $this->subject($actor, $context, $row),
        ));
    }

    /** @return LengthAwarePaginator<int, AuthorizationAdministrationCatalogItemDto> */
    public function roles(AuthorizationAdministrationContext $context, ?string $query, int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        [$page, $perPage] = $this->pagination($page, $perPage);
        $roles = AuthorizationRole::query()
            ->where('scope', $context->scope->value)
            ->when($context->scope === AuthorizationScope::Tenant, fn ($builder) => $builder->where(fn ($role) => $role->where('idTenant', $context->tenantId)->orWhereNull('idTenant')), fn ($builder) => $builder->whereNull('idTenant'))
            ->when($query !== null && trim($query) !== '', fn ($builder) => $builder->where(fn ($role) => $role->where('key', 'like', '%'.trim($query).'%')->orWhere('displayName', 'like', '%'.trim($query).'%')))
            ->orderBy('displayName')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $roles->setCollection($roles->getCollection()->map(static fn (AuthorizationRole $role): AuthorizationAdministrationCatalogItemDto => new AuthorizationAdministrationCatalogItemDto(
            $role->id,
            'ROLE',
            $role->key,
            $role->displayName,
            $role->description ?? '',
            $role->scope,
            $role->systemManaged,
            $role->active,
        )));
    }

    /** @return LengthAwarePaginator<int, AuthorizationAdministrationCatalogItemDto> */
    public function groups(AuthorizationAdministrationContext $context, ?string $query, int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        [$page, $perPage] = $this->pagination($page, $perPage);
        $groups = AuthorizationGroup::query()
            ->where('scope', $context->scope->value)
            ->when($context->scope === AuthorizationScope::Tenant, fn ($builder) => $builder->where('idTenant', $context->tenantId), fn ($builder) => $builder->whereNull('idTenant'))
            ->when($query !== null && trim($query) !== '', fn ($builder) => $builder->where('displayName', 'like', '%'.trim($query).'%'))
            ->orderBy('displayName')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $groups->setCollection($groups->getCollection()->map(static fn (AuthorizationGroup $group): AuthorizationAdministrationCatalogItemDto => new AuthorizationAdministrationCatalogItemDto(
            $group->id,
            'GROUP',
            'group.'.$group->id,
            $group->displayName,
            '',
            $group->scope,
            false,
            $group->active,
        )));
    }

    /** @return list<AuthorizationAdministrationCatalogItemDto> */
    public function permissions(AuthorizationAdministrationContext $context, ?string $query): array
    {
        return AuthorizationPermission::query()
            ->where('scope', $context->scope->value)
            ->when($query !== null && trim($query) !== '', fn ($builder) => $builder->where(fn ($permission) => $permission->where('key', 'like', '%'.trim($query).'%')->orWhere('displayName', 'like', '%'.trim($query).'%')))
            ->orderBy('displayName')
            ->orderBy('id')
            ->get()
            ->map(static fn (AuthorizationPermission $permission): AuthorizationAdministrationCatalogItemDto => new AuthorizationAdministrationCatalogItemDto(
                $permission->id,
                'PERMISSION',
                $permission->key,
                $permission->displayName,
                $permission->description ?? '',
                $permission->scope,
                $permission->systemManaged,
                $permission->active,
            ))
            ->all();
    }

    /** @return array{subject: AuthorizationAdministrationSubjectDto, effectiveCapabilities: list<string>, factors: array<string, mixed>} */
    public function effectiveAccess(User $actor, AuthorizationAdministrationContext $context, string $subjectType, int $subjectId): array
    {
        $subject = $this->visibleSubject($actor, $context, $subjectType, $subjectId);
        if ($subject->subjectType === 'SERVICE_IDENTITY') {
            $permissions = DB::table('auth_service_identity_permission')
                ->join('auth_permission', 'auth_permission.id', '=', 'auth_service_identity_permission.idPermission')
                ->where('auth_service_identity_permission.idServiceIdentity', $subjectId)
                ->where('auth_permission.active', true)
                ->where('auth_permission.scope', $context->scope->value)
                ->orderBy('auth_permission.key')
                ->pluck('auth_permission.key')
                ->all();

            return ['subject' => $subject, 'effectiveCapabilities' => $permissions, 'factors' => ['directPermissions' => $permissions]];
        }

        $user = User::query()->findOrFail($subjectId);
        if ($context->scope === AuthorizationScope::Tenant) {
            $factors = $this->effectiveAccess->forTenantMember($user, $context->tenantId);

            return ['subject' => $subject, 'effectiveCapabilities' => $this->allowedCapabilities($user, $context), 'factors' => $factors];
        }

        $permissions = $this->allowedCapabilities($user, $context);

        return ['subject' => $subject, 'effectiveCapabilities' => $permissions, 'factors' => ['authorizationEngine' => true]];
    }

    /** @return array{subject: AuthorizationAdministrationSubjectDto, allowed: bool, reasonCode: string} */
    public function explain(User $actor, AuthorizationAdministrationContext $context, string $subjectType, int $subjectId, string $permissionKey): array
    {
        $subject = $this->visibleSubject($actor, $context, $subjectType, $subjectId);
        $permission = AuthorizationPermission::query()->where('key', $permissionKey)->where('scope', $context->scope->value)->where('active', true)->first();
        if ($permission === null) {
            return ['subject' => $subject, 'allowed' => false, 'reasonCode' => 'PERMISSION_NOT_AVAILABLE'];
        }

        if ($subject->subjectType === 'SERVICE_IDENTITY') {
            $allowed = DB::table('auth_service_identity_permission')
                ->where('idServiceIdentity', $subjectId)
                ->where('idPermission', $permission->id)
                ->exists();

            return ['subject' => $subject, 'allowed' => $allowed, 'reasonCode' => $allowed ? 'DIRECT_GRANT_APPLIES' : 'NO_APPLICABLE_GRANT'];
        }

        $decision = $this->authorization->check(User::query()->findOrFail($subjectId), $permissionKey, $context->scope, $context->tenantId);

        return ['subject' => $subject, 'allowed' => $decision->allowed, 'reasonCode' => $decision->reasonCode];
    }

    /** @return LengthAwarePaginator<int, AuthorizationAdministrationAuditEventDto> */
    public function auditEvents(
        User $actor,
        AuthorizationAdministrationContext $context,
        ?string $operation,
        ?string $targetType,
        ?int $targetId,
        ?\DateTimeInterface $occurredAfter,
        ?\DateTimeInterface $occurredBefore,
        int $page = 1,
        int $perPage = 25,
    ): LengthAwarePaginator {
        [$page, $perPage] = $this->pagination($page, $perPage);
        if ($occurredAfter !== null && $occurredBefore !== null && $occurredAfter > $occurredBefore) {
            throw new InvalidArgumentException('Authorization administration audit period is invalid.');
        }

        $events = AuthorizationAuditEvent::query()
            ->when($context->scope === AuthorizationScope::Tenant, fn ($builder) => $builder->where('idTenant', $context->tenantId))
            ->when($context->scope === AuthorizationScope::Personal, fn ($builder) => $builder->whereNull('idTenant')->where('idActorUser', $actor->id))
            ->when($context->scope === AuthorizationScope::Platform, fn ($builder) => $builder->whereNull('idTenant'))
            ->when($operation !== null && trim($operation) !== '', fn ($builder) => $builder->where('operation', trim($operation)))
            ->when($targetType !== null && trim($targetType) !== '', fn ($builder) => $builder->where('targetType', trim($targetType)))
            ->when($targetId !== null, fn ($builder) => $builder->where('targetId', $targetId))
            ->when($occurredAfter !== null, fn ($builder) => $builder->where('occurredAt', '>=', $occurredAfter))
            ->when($occurredBefore !== null, fn ($builder) => $builder->where('occurredAt', '<=', $occurredBefore))
            ->orderByDesc('occurredAt')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $events->setCollection($events->getCollection()->map(static fn (AuthorizationAuditEvent $event): AuthorizationAdministrationAuditEventDto => new AuthorizationAdministrationAuditEventDto(
            $event->id,
            $event->occurredAt,
            $event->idActorUser,
            $event->operation,
            $event->targetType,
            $event->targetId,
        )));
    }

    /** @return LengthAwarePaginator<int, object> */
    private function subjectRows(User $actor, AuthorizationAdministrationContext $context, ?string $query, int $page, int $perPage): LengthAwarePaginator
    {
        if ($context->scope === AuthorizationScope::Tenant) {
            $users = DB::table('tenantMembership as membership')
                ->join('user as user', 'user.id', '=', 'membership.idUser')
                ->where('membership.idTenant', $context->tenantId)
                ->where('membership.state', TenantMembershipState::Active->value)
                ->selectRaw("user.id as subjectId, 'USER' as subjectType, user.displayName as displayName, NULL as expiresAt");
            $identities = DB::table('auth_service_identity')
                ->where('idTenant', $context->tenantId)
                ->where('scope', AuthorizationScope::Tenant->value)
                ->where('state', 'ACTIVE')
                ->where(fn ($builder) => $builder->whereNull('startsAt')->orWhere('startsAt', '<=', now()))
                ->where(fn ($builder) => $builder->whereNull('endsAt')->orWhere('endsAt', '>', now()))
                ->selectRaw("id as subjectId, 'SERVICE_IDENTITY' as subjectType, displayName, endsAt as expiresAt");
        } else {
            $users = User::query()->whereKey($actor->id)->selectRaw("id as subjectId, 'USER' as subjectType, displayName, NULL as expiresAt");
            $identities = DB::table('auth_service_identity')
                ->where('idOwnerUser', $actor->id)
                ->whereNull('idTenant')
                ->where('scope', $context->scope->value)
                ->where('state', 'ACTIVE')
                ->where(fn ($builder) => $builder->whereNull('startsAt')->orWhere('startsAt', '<=', now()))
                ->where(fn ($builder) => $builder->whereNull('endsAt')->orWhere('endsAt', '>', now()))
                ->selectRaw("id as subjectId, 'SERVICE_IDENTITY' as subjectType, displayName, endsAt as expiresAt");
        }

        return DB::query()
            ->fromSub($users->unionAll($identities), 'context_subject')
            ->when($query !== null && trim($query) !== '', fn ($builder) => $builder->where('displayName', 'like', '%'.trim($query).'%'))
            ->orderBy('displayName')
            ->orderBy('subjectId')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function visibleSubject(User $actor, AuthorizationAdministrationContext $context, string $subjectType, int $subjectId): AuthorizationAdministrationSubjectDto
    {
        if ($subjectId < 1 || ! in_array($subjectType, ['USER', 'SERVICE_IDENTITY'], true)) {
            throw new AuthorizationAdministrationSubjectNotAvailableException;
        }

        $row = $subjectType === 'USER'
            ? $this->visibleUserRow($actor, $context, $subjectId)
            : $this->visibleServiceIdentityRow($actor, $context, $subjectId);

        if ($row === null) {
            throw new AuthorizationAdministrationSubjectNotAvailableException;
        }

        return $this->subject($actor, $context, $row);
    }

    private function subject(User $actor, AuthorizationAdministrationContext $context, object $row): AuthorizationAdministrationSubjectDto
    {
        $sources = $row->subjectType === 'SERVICE_IDENTITY'
            ? [new AuthorizationAdministrationAccessSourceDto('DIRECT_GRANT', 'Permissões diretas da identidade de serviço', $context->scope->value)]
            : $this->resourceAccessSources((int) $row->subjectId, $context);

        return new AuthorizationAdministrationSubjectDto(
            (int) $row->subjectId,
            $row->subjectType,
            $row->displayName,
            $sources,
            [],
            $row->expiresAt === null ? null : now()->parse($row->expiresAt),
        );
    }

    private function visibleUserRow(User $actor, AuthorizationAdministrationContext $context, int $subjectId): ?object
    {
        if ($context->scope !== AuthorizationScope::Tenant) {
            return $actor->id === $subjectId ? (object) ['subjectId' => $actor->id, 'subjectType' => 'USER', 'displayName' => $actor->displayName, 'expiresAt' => null] : null;
        }

        return DB::table('tenantMembership as membership')
            ->join('user as user', 'user.id', '=', 'membership.idUser')
            ->where('membership.idTenant', $context->tenantId)
            ->where('membership.idUser', $subjectId)
            ->where('membership.state', TenantMembershipState::Active->value)
            ->selectRaw("user.id as subjectId, 'USER' as subjectType, user.displayName as displayName, NULL as expiresAt")
            ->first();
    }

    private function visibleServiceIdentityRow(User $actor, AuthorizationAdministrationContext $context, int $subjectId): ?object
    {
        return DB::table('auth_service_identity')
            ->where('id', $subjectId)
            ->where('idTenant', $context->tenantId)
            ->when($context->scope !== AuthorizationScope::Tenant, fn ($builder) => $builder->where('idOwnerUser', $actor->id)->whereNull('idTenant'))
            ->where('scope', $context->scope->value)
            ->where('state', 'ACTIVE')
            ->where(fn ($builder) => $builder->whereNull('startsAt')->orWhere('startsAt', '<=', now()))
            ->where(fn ($builder) => $builder->whereNull('endsAt')->orWhere('endsAt', '>', now()))
            ->selectRaw("id as subjectId, 'SERVICE_IDENTITY' as subjectType, displayName, endsAt as expiresAt")
            ->first();
    }

    /** @return list<AuthorizationAdministrationAccessSourceDto> */
    private function resourceAccessSources(int $subjectId, AuthorizationAdministrationContext $context): array
    {
        if ($context->scope !== AuthorizationScope::Tenant) {
            return [];
        }

        $resourceType = 'tenant.folder';
        $groupTenantPredicate = ' = ?';

        $rows = DB::select(
            "WITH RECURSIVE eligible_group(id, displayName) AS (
                SELECT direct_group.id, direct_group.displayName
                FROM auth_group_user
                INNER JOIN auth_group direct_group ON direct_group.id = auth_group_user.idGroup
                WHERE auth_group_user.idUser = ?
                    AND direct_group.active = 1
                    AND direct_group.scope = ?
                    AND direct_group.idTenant{$groupTenantPredicate}
                UNION
                SELECT parent_group.id, parent_group.displayName
                FROM auth_group_group group_relation
                INNER JOIN eligible_group ON eligible_group.id = group_relation.idChildGroup
                INNER JOIN auth_group parent_group ON parent_group.id = group_relation.idParentGroup
                WHERE parent_group.active = 1
                    AND parent_group.scope = ?
                    AND parent_group.idTenant{$groupTenantPredicate}
            )
            SELECT relation.resourceId, relation.idUser, relation.idGroup, eligible_group.displayName AS groupDisplayName
            FROM auth_resource_relation relation
            INNER JOIN auth_resource_type resource_type ON resource_type.id = relation.idResourceType
            LEFT JOIN eligible_group ON eligible_group.id = relation.idGroup
            WHERE resource_type.key = ?
                AND resource_type.active = 1
                AND relation.scope = ?
                AND relation.active = 1
                AND relation.idTenant = ?
                AND (relation.idUser = ? OR relation.idGroup IN (SELECT id FROM eligible_group))
            ORDER BY relation.resourceId, relation.id",
            $this->resourceAccessSourceParameters($subjectId, $context, $resourceType),
        );

        return array_map(static fn (object $row): AuthorizationAdministrationAccessSourceDto => new AuthorizationAdministrationAccessSourceDto(
            'SHARE',
            $row->idUser === null ? 'Pasta compartilhada pelo grupo '.$row->groupDisplayName : 'Pasta compartilhada diretamente',
            $context->scope->value,
            resource: ['resourceType' => 'FOLDER', 'resourceId' => (int) $row->resourceId],
        ), $rows);
    }

    /** @return list<int|string> */
    private function resourceAccessSourceParameters(int $subjectId, AuthorizationAdministrationContext $context, string $resourceType): array
    {
        return [$subjectId, $context->scope->value, $context->tenantId, $context->scope->value, $context->tenantId, $resourceType, $context->scope->value, $context->tenantId, $subjectId];
    }

    /** @return list<string> */
    /** @return list<string> */
    private function allowedCapabilities(User $subject, AuthorizationAdministrationContext $context): array
    {
        $keys = AuthorizationPermission::query()
            ->where('scope', $context->scope->value)
            ->where('active', true)
            ->orderBy('key')
            ->pluck('key')
            ->values()
            ->all();
        $decisions = $this->authorization->checkBatch($subject, array_map(
            fn (string $key): array => ['permissionKey' => $key, ...($context->tenantId === null ? [] : ['tenantId' => $context->tenantId])],
            $keys,
        ));

        return collect($keys)
            ->filter(fn (string $key, int $index): bool => $decisions[$index]['allowed'])
            ->values()
            ->all();
    }

    /** @return array{0: int, 1: int} */
    private function pagination(int $page, int $perPage): array
    {
        if ($page < 1 || $perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Authorization administration pagination is invalid.');
        }

        return [$page, $perPage];
    }
}
