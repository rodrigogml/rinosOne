<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\Provisioning\TenantProvisioningPolicy;
use App\Domain\Tenant\SchemaUpdate\SchemaUpdateFailureClassification;
use App\Domain\Tenant\SchemaUpdate\TenantSchemaUpdateAttempt;
use App\Domain\Tenant\SchemaUpdate\TenantSchemaUpdateFailure;
use App\Domain\Tenant\TenantSchemaUpdateState;
use App\Models\TenantSchemaUpdate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Coordinates the serial and retryable lifecycle of an existing tenant schema
 * update without changing the tenant's provisioning lifecycle.
 */
class TenantSchemaUpdateLifecycle
{
    public function __construct(private readonly TenantProvisioningPolicy $policy) {}

    public function queue(string $tenantId, string $targetCatalog): TenantSchemaUpdate
    {
        if (! ctype_digit($tenantId) || (int) $tenantId < 1 || trim($targetCatalog) === '') {
            throw new InvalidArgumentException('The tenant schema update target is invalid.');
        }

        $existing = TenantSchemaUpdate::query()
            ->where('idTenant', $tenantId)
            ->where('targetCatalog', $targetCatalog)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return TenantSchemaUpdate::query()->create([
                'idTenant' => $tenantId,
                'targetCatalog' => $targetCatalog,
                'state' => TenantSchemaUpdateState::Queued,
                'attemptCount' => 0,
            ]);
        } catch (QueryException $exception) {
            $existing = TenantSchemaUpdate::query()
                ->where('idTenant', $tenantId)
                ->where('targetCatalog', $targetCatalog)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function claim(string $updateId): ?TenantSchemaUpdateAttempt
    {
        return DB::transaction(function () use ($updateId): ?TenantSchemaUpdateAttempt {
            $update = DB::table('tenantSchemaUpdate')
                ->where('id', $updateId)
                ->lockForUpdate()
                ->first();

            if ($update === null || $update->state !== TenantSchemaUpdateState::Queued->value) {
                return null;
            }

            if ((int) $update->attemptCount >= $this->policy->maximumAttempts()) {
                $this->markFailed($updateId, 'TENANT_SCHEMA_UPDATE_ATTEMPTS_EXHAUSTED');

                return null;
            }

            $now = now();
            $claimed = DB::table('tenantSchemaUpdate')
                ->where('id', $updateId)
                ->where('state', TenantSchemaUpdateState::Queued->value)
                ->update([
                    'state' => TenantSchemaUpdateState::Running->value,
                    'attemptCount' => (int) $update->attemptCount + 1,
                    'startedAt' => $now,
                    'updatedAt' => $now,
                ]);

            if ($claimed !== 1) {
                return null;
            }

            return new TenantSchemaUpdateAttempt(
                $updateId,
                $update->idTenant,
                $update->targetCatalog,
                (int) $update->attemptCount + 1,
            );
        });
    }

    public function succeed(TenantSchemaUpdateAttempt $attempt): bool
    {
        $now = now();

        return DB::table('tenantSchemaUpdate')
            ->where('id', $attempt->id)
            ->where('state', TenantSchemaUpdateState::Running->value)
            ->update([
                'state' => TenantSchemaUpdateState::Succeeded->value,
                'lastFailureCode' => null,
                'completedAt' => $now,
                'updatedAt' => $now,
            ]) === 1;
    }

    public function fail(TenantSchemaUpdateAttempt $attempt, TenantSchemaUpdateFailure $failure): ?int
    {
        return DB::transaction(function () use ($attempt, $failure): ?int {
            $update = DB::table('tenantSchemaUpdate')
                ->where('id', $attempt->id)
                ->lockForUpdate()
                ->first();

            if ($update === null || $update->state !== TenantSchemaUpdateState::Running->value) {
                return null;
            }

            $retryDelay = $failure->classification === SchemaUpdateFailureClassification::Transient
                && (int) $update->attemptCount < $this->policy->maximumAttempts()
                ? $this->policy->retryDelaySeconds((int) $update->attemptCount)
                : null;

            if ($retryDelay !== null) {
                DB::table('tenantSchemaUpdate')
                    ->where('id', $attempt->id)
                    ->where('state', TenantSchemaUpdateState::Running->value)
                    ->update([
                        'state' => TenantSchemaUpdateState::Queued->value,
                        'lastFailureCode' => $failure->code,
                        'startedAt' => null,
                        'updatedAt' => now(),
                    ]);

                return $retryDelay;
            }

            $this->markFailed($attempt->id, $failure->code);

            return null;
        });
    }

    public function purgeCompletedBefore(\DateTimeInterface $before): int
    {
        return DB::table('tenantSchemaUpdate')
            ->whereIn('state', [TenantSchemaUpdateState::Succeeded->value, TenantSchemaUpdateState::Failed->value])
            ->whereNotNull('completedAt')
            ->where('completedAt', '<', $before)
            ->delete();
    }

    public function purgeExpired(): int
    {
        $retentionDays = config('schema-compatibility.tenantUpdateRetentionDays');

        if (! is_int($retentionDays) || $retentionDays < 1) {
            throw new \LogicException('The tenant schema update retention configuration is invalid.');
        }

        return $this->purgeCompletedBefore(now()->subDays($retentionDays));
    }

    private function markFailed(string $updateId, string $failureCode): void
    {
        $now = now();

        DB::table('tenantSchemaUpdate')
            ->where('id', $updateId)
            ->whereIn('state', [TenantSchemaUpdateState::Queued->value, TenantSchemaUpdateState::Running->value])
            ->update([
                'state' => TenantSchemaUpdateState::Failed->value,
                'lastFailureCode' => $failureCode,
                'completedAt' => $now,
                'updatedAt' => $now,
            ]);
    }
}
