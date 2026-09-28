<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationDelegation;
use App\Models\AuthorizationPermission;
use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\Performance\PolicyVersionService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Persists a bounded, auditable delegation; effective authorization is validated separately. */
class AuthorizationDelegationService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit, private readonly PolicyVersionService $policyVersions) {}

    /** @param array<string, scalar|array|null>|null $limits */
    public function create(User $delegator, User $recipient, AuthorizationPermission $permission, AuthorizationScope $scope, ?int $tenantId, string $originType, int $originId, DateTimeInterface $startsAt, DateTimeInterface $endsAt, ?array $limits = null, ?int $actorUserId = null): AuthorizationDelegation
    {
        if ($delegator->id === $recipient->id || ! $permission->active || $permission->scope !== $scope->value || $originId < 1 || trim($originType) === '') {
            throw new LogicException('The authorization delegation is invalid.');
        }
        if (($scope === AuthorizationScope::Tenant) !== ($tenantId !== null) || $endsAt <= $startsAt || $endsAt > now()->addDays((int) config('authorization.maxDelegationDays', 30))) {
            throw new LogicException('The authorization delegation validity is invalid.');
        }

        return DB::transaction(function () use ($delegator, $recipient, $permission, $scope, $tenantId, $originType, $originId, $startsAt, $endsAt, $limits, $actorUserId): AuthorizationDelegation {
            if ($scope === AuthorizationScope::Tenant && (! Tenant::query()->whereKey($tenantId)->where('state', TenantState::Active)->lockForUpdate()->exists()
                || ! TenantMembership::query()->where('idTenant', $tenantId)->where('idUser', $recipient->id)->where('state', TenantMembershipState::Active)->lockForUpdate()->exists())) {
                throw new LogicException('A tenant authorization delegation requires an active tenant and recipient membership.');
            }
            if ($originType !== 'ROLE_ASSIGNMENT' || ! DB::table('auth_role_assignment')
                ->join('auth_role_permission', 'auth_role_permission.idRole', '=', 'auth_role_assignment.idRole')
                ->where('auth_role_assignment.id', $originId)->where('auth_role_assignment.idUser', $delegator->id)
                ->where('auth_role_assignment.state', 'ACTIVE')->where('auth_role_permission.idPermission', $permission->id)
                ->when($scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_role_assignment.idTenant', $tenantId), fn ($query) => $query->whereNull('auth_role_assignment.idTenant'))
                ->exists()) {
                throw new LogicException('The authorization delegation origin is not an active grant of the delegator.');
            }
            $delegation = AuthorizationDelegation::query()->create([
                'idDelegatorUser' => $delegator->id, 'idRecipientUser' => $recipient->id, 'idPermission' => $permission->id,
                'idTenant' => $tenantId, 'scope' => $scope->value, 'originType' => $originType, 'originId' => $originId,
                'limits' => $limits, 'startsAt' => $startsAt, 'endsAt' => $endsAt, 'state' => 'ACTIVE',
            ]);
            $this->audit->record('authorization.delegation.created', 'authorization.delegation', $delegation->id, actorUserId: $actorUserId, tenantId: $tenantId, after: ['idDelegatorUser' => $delegator->id, 'idRecipientUser' => $recipient->id, 'idPermission' => $permission->id, 'originType' => $originType, 'originId' => $originId, 'state' => 'ACTIVE']);
            $this->policyVersions->invalidate($scope, $tenantId);

            return $delegation;
        });
    }

    public function revoke(AuthorizationDelegation $delegation, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($delegation, $actorUserId): void {
            $delegation = AuthorizationDelegation::query()->lockForUpdate()->findOrFail($delegation->id);
            if ($delegation->state !== 'ACTIVE') {
                return;
            }
            $delegation->update(['state' => 'REVOKED']);
            $this->audit->record('authorization.delegation.revoked', 'authorization.delegation', $delegation->id, actorUserId: $actorUserId, tenantId: $delegation->idTenant, before: ['state' => 'ACTIVE'], after: ['state' => 'REVOKED']);
            $this->policyVersions->invalidate(AuthorizationScope::from($delegation->scope), $delegation->idTenant);
        });
    }
}
