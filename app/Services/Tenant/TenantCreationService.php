<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\TenantCreationResult;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantProvisioningState;
use App\Domain\Tenant\TenantSecurityEvent;
use App\Domain\Tenant\TenantState;
use App\Jobs\ProvisionTenantSchema;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantProvisioning;
use App\Models\User;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\AuthorizationRoleAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantCreationService
{
    public function __construct(
        private readonly TenantSecurityEventLogger $securityEvents,
        private readonly AuthorizationRoleAssignmentService $roleAssignments,
        private readonly AuthorizationAuditLogger $authorizationAudit,
    ) {}

    public function create(User $creator, string $displayName, string $idempotencyKey): TenantCreationResult
    {
        $displayName = trim($displayName);

        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new InvalidArgumentException('The tenant display name is invalid.');
        }

        if (! Str::isUuid($idempotencyKey)) {
            throw new InvalidArgumentException('The idempotency key must be a valid UUID.');
        }

        try {
            $result = DB::transaction(function () use ($creator, $displayName, $idempotencyKey): TenantCreationResult {
                $existing = $this->findExisting($creator->id, $idempotencyKey, true);

                if ($existing !== null) {
                    return new TenantCreationResult($existing->tenant, $existing, false);
                }

                $tenant = Tenant::query()->create([
                    'displayName' => $displayName,
                    'state' => TenantState::Provisioning,
                ]);

                $membership = TenantMembership::query()->create([
                    'idTenant' => $tenant->id,
                    'idUser' => $creator->id,
                    'state' => TenantMembershipState::Active,
                ]);
                $this->authorizationAudit->record(
                    'authorization.tenant_membership.activated',
                    'tenant.membership',
                    $membership->id,
                    actorUserId: $creator->id,
                    tenantId: $tenant->id,
                    after: ['idUser' => $creator->id, 'state' => $membership->state->value],
                    correlationId: $idempotencyKey,
                );

                $this->roleAssignments->assignTenantAdministrator($creator, $tenant->id, $creator->id, $idempotencyKey);

                $provisioning = TenantProvisioning::query()->create([
                    'idTenant' => $tenant->id,
                    'idRequestedByUser' => $creator->id,
                    'idempotencyKey' => $idempotencyKey,
                    'state' => TenantProvisioningState::Queued,
                    'attemptCount' => 0,
                ]);

                ProvisionTenantSchema::dispatch($provisioning->id)->afterCommit();

                return new TenantCreationResult($tenant, $provisioning, true);
            });
            if ($result->created) {
                $this->securityEvents->record(TenantSecurityEvent::Created, $creator->id);
            }

            return $result;
        } catch (QueryException $exception) {
            $existing = $this->findExisting($creator->id, $idempotencyKey);

            if ($existing === null) {
                throw $exception;
            }

            return new TenantCreationResult($existing->tenant, $existing, false);
        }
    }

    private function findExisting(int $creatorId, string $idempotencyKey, bool $lock = false): ?TenantProvisioning
    {
        $query = TenantProvisioning::query()
            ->with('tenant')
            ->where('idRequestedByUser', $creatorId)
            ->where('idempotencyKey', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
