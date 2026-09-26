<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationGroup;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationGroupService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit) {}

    public function create(string $displayName, AuthorizationScope $scope, ?int $tenantId = null, ?int $actorUserId = null, ?string $correlationId = null): AuthorizationGroup
    {
        $displayName = trim($displayName);
        if ($displayName === '' || mb_strlen($displayName) > 160) {
            throw new LogicException('The authorization group display name is invalid.');
        }

        return DB::transaction(function () use ($displayName, $scope, $tenantId, $actorUserId, $correlationId): AuthorizationGroup {
            $this->validateScopeContext($scope, $tenantId);
            $group = AuthorizationGroup::query()->create([
                'idTenant' => $tenantId,
                'displayName' => $displayName,
                'scope' => $scope->value,
                'active' => true,
            ]);
            $this->audit->record(
                'authorization.group.created',
                'authorization.group',
                $group->id,
                actorUserId: $actorUserId,
                tenantId: $tenantId,
                after: ['displayName' => $group->displayName, 'scope' => $group->scope, 'active' => $group->active],
                correlationId: $correlationId,
            );

            return $group;
        });
    }

    public function addUser(AuthorizationGroup $group, User $user, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($group, $user, $actorUserId, $correlationId): void {
            $group = AuthorizationGroup::query()->lockForUpdate()->findOrFail($group->id);
            $this->ensureGroupAcceptsMembers($group, $user);

            $created = DB::table('auth_group_user')->insertOrIgnore([
                'idGroup' => $group->id,
                'idUser' => $user->id,
            ]);
            if ($created === 1) {
                $this->audit->record(
                    'authorization.group_user.added',
                    'authorization.group',
                    $group->id,
                    actorUserId: $actorUserId,
                    tenantId: $group->idTenant,
                    after: ['idUser' => $user->id],
                    correlationId: $correlationId,
                );
            }
        });
    }

    public function removeUser(AuthorizationGroup $group, User $user, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($group, $user, $actorUserId, $correlationId): void {
            $group = AuthorizationGroup::query()->lockForUpdate()->findOrFail($group->id);
            $deleted = DB::table('auth_group_user')->where('idGroup', $group->id)->where('idUser', $user->id)->delete();
            if ($deleted === 1) {
                $this->audit->record(
                    'authorization.group_user.removed',
                    'authorization.group',
                    $group->id,
                    actorUserId: $actorUserId,
                    tenantId: $group->idTenant,
                    before: ['idUser' => $user->id],
                    correlationId: $correlationId,
                );
            }
        });
    }

    public function deactivate(AuthorizationGroup $group, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($group, $actorUserId, $correlationId): void {
            $group = AuthorizationGroup::query()->lockForUpdate()->findOrFail($group->id);
            if (! $group->active) {
                return;
            }

            $group->active = false;
            $group->save();
            $this->audit->record(
                'authorization.group.deactivated',
                'authorization.group',
                $group->id,
                actorUserId: $actorUserId,
                tenantId: $group->idTenant,
                before: ['active' => true],
                after: ['active' => false],
                correlationId: $correlationId,
            );
        });
    }

    private function validateScopeContext(AuthorizationScope $scope, ?int $tenantId): void
    {
        if ($scope !== AuthorizationScope::Tenant && $tenantId !== null) {
            throw new LogicException('Only tenant authorization groups can have a tenant context.');
        }

        if ($scope !== AuthorizationScope::Tenant) {
            return;
        }

        if ($tenantId === null || ! Tenant::query()->whereKey($tenantId)->where('state', TenantState::Active->value)->lockForUpdate()->exists()) {
            throw new LogicException('A tenant authorization group requires an active tenant context.');
        }
    }

    private function ensureGroupAcceptsMembers(AuthorizationGroup $group, User $user): void
    {
        if (! $group->active) {
            throw new LogicException('Inactive authorization groups cannot receive members.');
        }

        if ($group->scope !== AuthorizationScope::Tenant->value) {
            return;
        }

        $eligible = TenantMembership::query()
            ->where('idTenant', $group->idTenant)
            ->where('idUser', $user->id)
            ->where('state', TenantMembershipState::Active->value)
            ->lockForUpdate()
            ->exists();

        if (! $eligible) {
            throw new LogicException('A tenant authorization group requires an active tenant membership.');
        }
    }
}
