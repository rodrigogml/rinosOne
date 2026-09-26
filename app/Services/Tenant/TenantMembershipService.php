<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\TenantMembershipState;
use App\Models\TenantMembership;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\TenantAdministratorInvariant;
use Illuminate\Support\Facades\DB;

class TenantMembershipService
{
    public function __construct(
        private readonly AuthorizationAuditLogger $audit,
        private readonly TenantAdministratorInvariant $administrators,
    ) {}

    public function activate(TenantMembership $membership, ?int $actorUserId = null, ?string $correlationId = null): TenantMembership
    {
        return DB::transaction(function () use ($membership, $actorUserId, $correlationId): TenantMembership {
            $membership = TenantMembership::query()->lockForUpdate()->findOrFail($membership->id);
            if ($membership->state === TenantMembershipState::Active) {
                return $membership;
            }

            $before = ['state' => $membership->state->value];
            $membership->update(['state' => TenantMembershipState::Active->value]);
            $this->audit->record(
                'authorization.tenant_membership.activated',
                'tenant.membership',
                $membership->id,
                actorUserId: $actorUserId,
                tenantId: $membership->idTenant,
                before: $before,
                after: ['idUser' => $membership->idUser, 'state' => $membership->state->value],
                correlationId: $correlationId,
            );

            return $membership;
        });
    }

    public function deactivate(TenantMembership $membership, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($membership, $actorUserId, $correlationId): void {
            $membership = TenantMembership::query()->lockForUpdate()->findOrFail($membership->id);
            if ($membership->state === TenantMembershipState::Inactive) {
                return;
            }
            $this->ensureMembershipChangeKeepsAdministrator($membership);

            $before = ['state' => $membership->state->value];
            $membership->update(['state' => TenantMembershipState::Inactive->value]);
            $this->audit->record(
                'authorization.tenant_membership.deactivated',
                'tenant.membership',
                $membership->id,
                actorUserId: $actorUserId,
                tenantId: $membership->idTenant,
                before: $before,
                after: ['state' => $membership->state->value],
                correlationId: $correlationId,
            );
        });
    }

    public function remove(TenantMembership $membership, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($membership, $actorUserId, $correlationId): void {
            $membership = TenantMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $this->ensureMembershipChangeKeepsAdministrator($membership);

            $before = ['idUser' => $membership->idUser, 'state' => $membership->state->value];
            $membershipId = $membership->id;
            $membership->delete();
            $this->audit->record(
                'authorization.tenant_membership.removed',
                'tenant.membership',
                $membershipId,
                actorUserId: $actorUserId,
                tenantId: $membership->idTenant,
                before: $before,
                correlationId: $correlationId,
            );
        });
    }

    private function ensureMembershipChangeKeepsAdministrator(TenantMembership $membership): void
    {
        if ($this->administrators->isActiveDirectAdministrator($membership->user, $membership->idTenant)) {
            $this->administrators->ensureAnotherActiveDirectAdministrator($membership->idTenant, excludedUserId: $membership->idUser);
        }
    }
}
