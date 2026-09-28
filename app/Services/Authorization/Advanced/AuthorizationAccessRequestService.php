<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationAccessRequest;
use App\Models\AuthorizationPermission;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\Performance\PolicyVersionService;
use App\Services\Authorization\TenantAdministratorInvariant;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationAccessRequestService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit, private readonly TenantAdministratorInvariant $administrators, private readonly PolicyVersionService $policyVersions) {}

    public function request(User $requester, User $recipient, AuthorizationPermission $permission, AuthorizationScope $scope, ?int $tenantId, DateTimeInterface $startsAt, DateTimeInterface $endsAt): AuthorizationAccessRequest
    {
        if ($requester->id !== $recipient->id || $scope !== AuthorizationScope::Tenant || ! $permission->active || $permission->scope !== $scope->value || $tenantId === null || ! Tenant::query()->whereKey($tenantId)->where('state', TenantState::Active)->exists() || ! TenantMembership::query()->where('idTenant', $tenantId)->where('idUser', $recipient->id)->where('state', TenantMembershipState::Active)->exists() || $endsAt <= $startsAt || $endsAt > now()->addDays((int) config('authorization.maxDelegationDays', 30))) {
            throw new LogicException('The authorization access request is invalid.');
        }
        $request = AuthorizationAccessRequest::query()->create(['idRequesterUser' => $requester->id, 'idRecipientUser' => $recipient->id, 'idPermission' => $permission->id, 'idTenant' => $tenantId, 'scope' => $scope->value, 'startsAt' => $startsAt, 'endsAt' => $endsAt, 'state' => 'PENDING']);
        $this->audit->record('authorization.access_request.created', 'authorization.access_request', $request->id, actorUserId: $requester->id, tenantId: $tenantId, after: ['state' => 'PENDING', 'idPermission' => $permission->id]);
        $this->policyVersions->invalidate($scope, $tenantId);

        return $request;
    }

    public function approve(AuthorizationAccessRequest $request, User $approver): void
    {
        DB::transaction(function () use ($request, $approver): void {
            $request = AuthorizationAccessRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->state !== 'PENDING' || $request->idTenant === null || $request->endsAt <= now() || $approver->id === $request->idRequesterUser || $approver->id === $request->idRecipientUser || ! $this->administrators->isActiveDirectAdministrator($approver, $request->idTenant)) {
                throw new LogicException('The authorization access request cannot be approved by this subject.');
            }
            $request->update(['state' => 'APPROVED', 'idApproverUser' => $approver->id, 'decidedAt' => now()]);
            $this->audit->record('authorization.access_request.approved', 'authorization.access_request', $request->id, actorUserId: $approver->id, tenantId: $request->idTenant, before: ['state' => 'PENDING'], after: ['state' => 'APPROVED']);
            $this->policyVersions->invalidate(AuthorizationScope::from($request->scope), $request->idTenant);
        });
    }

    public function reject(AuthorizationAccessRequest $request, User $approver): void
    {
        DB::transaction(function () use ($request, $approver): void {
            $request = AuthorizationAccessRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->state !== 'PENDING' || $request->idTenant === null || $approver->id === $request->idRequesterUser || $approver->id === $request->idRecipientUser || ! $this->administrators->isActiveDirectAdministrator($approver, $request->idTenant)) {
                throw new LogicException('The authorization access request cannot be rejected by this subject.');
            }
            $request->update(['state' => 'REJECTED', 'idApproverUser' => $approver->id, 'decidedAt' => now()]);
            $this->audit->record('authorization.access_request.rejected', 'authorization.access_request', $request->id, actorUserId: $approver->id, tenantId: $request->idTenant, before: ['state' => 'PENDING'], after: ['state' => 'REJECTED']);
            $this->policyVersions->invalidate(AuthorizationScope::from($request->scope), $request->idTenant);
        });
    }

    /** Cancels a pending or approved request by its requester or a tenant administrator. */
    public function revoke(AuthorizationAccessRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor): void {
            $request = AuthorizationAccessRequest::query()->lockForUpdate()->findOrFail($request->id);
            $isRequester = $actor->id === $request->idRequesterUser;
            $isAdministrator = $request->idTenant !== null && $this->administrators->isActiveDirectAdministrator($actor, $request->idTenant);

            if (! in_array($request->state, ['PENDING', 'APPROVED'], true) || (! $isRequester && ! $isAdministrator)) {
                throw new LogicException('The authorization access request cannot be revoked by this subject.');
            }

            $before = ['state' => $request->state];
            $request->update(['state' => 'REVOKED', 'decidedAt' => now()]);
            $this->audit->record('authorization.access_request.revoked', 'authorization.access_request', $request->id, actorUserId: $actor->id, tenantId: $request->idTenant, before: $before, after: ['state' => 'REVOKED']);
            $this->policyVersions->invalidate(AuthorizationScope::from($request->scope), $request->idTenant);
        });
    }

    public function expireDue(): int
    {
        return DB::transaction(function (): int {
            $requests = AuthorizationAccessRequest::query()->whereIn('state', ['PENDING', 'APPROVED'])->where('endsAt', '<=', now())->lockForUpdate()->get();
            foreach ($requests as $request) {
                $before = ['state' => $request->state];
                $request->update(['state' => 'EXPIRED']);
                $this->audit->record('authorization.access_request.expired', 'authorization.access_request', $request->id, tenantId: $request->idTenant, before: $before, after: ['state' => 'EXPIRED']);
                $this->policyVersions->invalidate(AuthorizationScope::from($request->scope), $request->idTenant);
            }

            return $requests->count();
        });
    }
}
