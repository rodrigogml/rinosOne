<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\Provisioning\ProvisioningFailureClassification;
use App\Domain\Tenant\Provisioning\TenantProvisioningAttempt;
use App\Domain\Tenant\Provisioning\TenantProvisioningFailure;
use App\Domain\Tenant\Provisioning\TenantProvisioningPolicy;
use Illuminate\Support\Facades\DB;

class TenantProvisioningLifecycle
{
    public function __construct(private readonly TenantProvisioningPolicy $policy) {}

    public function claim(string $provisioningId): ?TenantProvisioningAttempt
    {
        return DB::transaction(function () use ($provisioningId): ?TenantProvisioningAttempt {
            $provisioning = DB::table('tenantProvisioning')
                ->where('id', $provisioningId)
                ->lockForUpdate()
                ->first();

            if ($provisioning === null || $provisioning->state !== 'QUEUED') {
                return null;
            }

            if ((int) $provisioning->attemptCount >= $this->policy->maximumAttempts()) {
                $this->markFailed($provisioningId, $provisioning->idTenant, 'TENANT_PROVISIONING_ATTEMPTS_EXHAUSTED');

                return null;
            }

            DB::table('tenantProvisioning')
                ->where('id', $provisioningId)
                ->where('state', 'QUEUED')
                ->update([
                    'state' => 'RUNNING',
                    'attemptCount' => (int) $provisioning->attemptCount + 1,
                    'updatedAt' => now(),
                ]);

            return new TenantProvisioningAttempt(
                $provisioningId,
                $provisioning->idTenant,
                (int) $provisioning->attemptCount + 1,
            );
        });
    }

    public function succeed(TenantProvisioningAttempt $attempt): bool
    {
        return DB::transaction(function () use ($attempt): bool {
            $provisioningUpdated = DB::table('tenantProvisioning')
                ->where('id', $attempt->id)
                ->where('state', 'RUNNING')
                ->update([
                    'state' => 'SUCCEEDED',
                    'completedAt' => now(),
                    'lastFailureCode' => null,
                    'updatedAt' => now(),
                ]);

            if ($provisioningUpdated !== 1) {
                return false;
            }

            $tenantUpdated = DB::table('tenant')
                ->where('id', $attempt->tenantId)
                ->where('state', 'PROVISIONING')
                ->update([
                    'state' => 'ACTIVE',
                    'updatedAt' => now(),
                ]);

            if ($tenantUpdated !== 1) {
                throw new \LogicException('The tenant could not be activated after provisioning.');
            }

            return true;
        });
    }

    public function fail(TenantProvisioningAttempt $attempt, TenantProvisioningFailure $failure): ?int
    {
        return DB::transaction(function () use ($attempt, $failure): ?int {
            $provisioning = DB::table('tenantProvisioning')
                ->where('id', $attempt->id)
                ->lockForUpdate()
                ->first();

            if ($provisioning === null || $provisioning->state !== 'RUNNING') {
                return null;
            }

            $retryDelay = $failure->classification === ProvisioningFailureClassification::TRANSIENT
                && (int) $provisioning->attemptCount < $this->policy->maximumAttempts()
                ? $this->policy->retryDelaySeconds((int) $provisioning->attemptCount)
                : null;

            if ($retryDelay !== null) {
                DB::table('tenantProvisioning')
                    ->where('id', $attempt->id)
                    ->where('state', 'RUNNING')
                    ->update([
                        'state' => 'QUEUED',
                        'lastFailureCode' => $failure->code,
                        'updatedAt' => now(),
                    ]);

                return $retryDelay;
            }

            $this->markFailed($attempt->id, $attempt->tenantId, $failure->code);

            return null;
        });
    }

    private function markFailed(string $provisioningId, string $tenantId, string $failureCode): void
    {
        DB::table('tenantProvisioning')
            ->where('id', $provisioningId)
            ->whereIn('state', ['QUEUED', 'RUNNING'])
            ->update([
                'state' => 'FAILED',
                'lastFailureCode' => $failureCode,
                'updatedAt' => now(),
            ]);

        DB::table('tenant')
            ->where('id', $tenantId)
            ->where('state', 'PROVISIONING')
            ->update([
                'state' => 'FAILED',
                'updatedAt' => now(),
            ]);
    }
}
