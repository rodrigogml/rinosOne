<?php

namespace App\Services\Person;

use App\Domain\Tenant\TenantProvisioningState;
use App\Infrastructure\Tenant\TenantDatabaseConnectionFactory;
use App\Models\TenantProvisioning;
use Carbon\CarbonInterface;
use LogicException;

class PersonAuditRetentionService
{
    public function __construct(private readonly TenantDatabaseConnectionFactory $connections) {}

    /**
     * Removes expired Person audit events only from successfully provisioned
     * tenant schemas. Provisioning or failed schemas are intentionally skipped.
     */
    public function purgeExpired(?CarbonInterface $now = null): PersonAuditRetentionResult
    {
        $retentionDays = config('person.auditRetentionDays');
        if (! is_int($retentionDays) || $retentionDays < 1) {
            throw new LogicException('PERSON_AUDIT_RETENTION_DAYS must be a positive integer.');
        }

        $expiresAt = ($now ?? now())->copy()->subDays($retentionDays);
        $tenantIds = TenantProvisioning::query()
            ->where('state', TenantProvisioningState::Succeeded)
            ->pluck('idTenant');
        $deletedEventCount = 0;

        foreach ($tenantIds as $tenantId) {
            $deletedEventCount += $this->connections->connection((string) $tenantId)
                ->table('personAuditEvent')
                ->where('occurredAt', '<=', $expiresAt)
                ->delete();
        }

        return new PersonAuditRetentionResult($tenantIds->count(), $deletedEventCount);
    }
}
