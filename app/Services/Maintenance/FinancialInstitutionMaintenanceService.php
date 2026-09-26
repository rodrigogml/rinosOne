<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceAdministrativeAudit;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\FinancialInstitution\FinancialInstitutionSynchronizationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class FinancialInstitutionMaintenanceService
{
    public const ROUTINE_KEY = 'financial-institution-catalog';

    public const READ_PERMISSION_KEY = 'platform.maintenance.financial-institution.read';

    public const SYNCHRONIZE_PERMISSION_KEY = 'platform.maintenance.financial-institution.synchronize';

    public function __construct(private readonly FinancialInstitutionSynchronizationService $synchronization) {}

    public function synchronizeManually(User $performedByUser): FinancialInstitutionMaintenanceResult
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $lock = Cache::lock($this->lockKey(), config('financial-institutions.maintenance_lock_seconds'));

        if (! $lock->get()) {
            MaintenanceAdministrativeAudit::query()->create([
                'idPerformedByUser' => $performedByUser->id,
                'routineKey' => self::ROUTINE_KEY,
                'action' => 'SYNCHRONIZE',
                'outcome' => 'REFUSED_ALREADY_RUNNING',
                'occurredAt' => $now,
                'expiresAt' => $now->addDays(config('maintenance.administrativeAuditRetentionDays')),
            ]);

            return new FinancialInstitutionMaintenanceResult(false, null, 'ALREADY_RUNNING');
        }

        try {
            $this->recordAcceptedManualAudit($performedByUser, $now);

            return new FinancialInstitutionMaintenanceResult(true, $this->synchronize('MANUAL', $now));
        } finally {
            $lock->release();
        }
    }

    public function synchronizeScheduled(): ?MaintenanceExecutionHistory
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $lock = Cache::lock($this->lockKey(), config('financial-institutions.maintenance_lock_seconds'));

        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->synchronize('SCHEDULED', $now);
        } finally {
            $lock->release();
        }
    }

    private function recordAcceptedManualAudit(User $performedByUser, CarbonImmutable $now): void
    {
        MaintenanceAdministrativeAudit::query()->create([
            'idPerformedByUser' => $performedByUser->id,
            'routineKey' => self::ROUTINE_KEY,
            'action' => 'SYNCHRONIZE',
            'outcome' => 'ACCEPTED',
            'occurredAt' => $now,
            'expiresAt' => $now->addDays(config('maintenance.administrativeAuditRetentionDays')),
        ]);
    }

    private function synchronize(string $triggerType, CarbonImmutable $now): MaintenanceExecutionHistory
    {
        $history = MaintenanceExecutionHistory::query()->create([
            'routineKey' => self::ROUTINE_KEY,
            'triggerType' => $triggerType,
            'state' => 'RUNNING',
            'startedAt' => $now,
            'expiresAt' => $now->addDays(config('maintenance.historyRetentionDays')),
        ]);
        $result = $this->synchronization->synchronize();

        $history->update([
            'state' => $result->succeeded ? 'SUCCEEDED' : 'FAILED',
            'startedAt' => $result->startedAt,
            'completedAt' => $result->finishedAt,
            'summary' => $result->succeeded
                ? "Created {$result->createdCount}; updated {$result->updatedCount}."
                : 'The official source could not be synchronized.',
            'details' => $result->succeeded
                ? ['createdCount' => $result->createdCount, 'updatedCount' => $result->updatedCount]
                : ['failureCode' => $result->failureCode],
        ]);

        return $history;
    }

    private function lockKey(): string
    {
        return 'maintenance.'.self::ROUTINE_KEY;
    }
}
