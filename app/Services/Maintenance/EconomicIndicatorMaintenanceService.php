<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceAdministrativeAudit;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\EconomicIndicator\EconomicIndicatorSynchronizationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Coordinates the authorized maintenance execution for global economic indicators.
 */
final class EconomicIndicatorMaintenanceService
{
    public const ROUTINE_KEY = 'economic-indicators';

    public const READ_PERMISSION_KEY = 'platform.maintenance.economic-indicators.read';

    public const SYNCHRONIZE_PERMISSION_KEY = 'platform.maintenance.economic-indicators.synchronize';

    public function __construct(private readonly EconomicIndicatorSynchronizationService $synchronization) {}

    public function synchronizeManually(User $performedByUser): EconomicIndicatorMaintenanceResult
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $lock = Cache::lock($this->lockKey(), config('economic-indicators.maintenance_lock_seconds'));

        if (! $lock->get()) {
            $this->recordAudit($performedByUser, 'REFUSED_ALREADY_RUNNING', $now);

            return new EconomicIndicatorMaintenanceResult(false, null, 'ALREADY_RUNNING');
        }

        try {
            $this->recordAudit($performedByUser, 'ACCEPTED', $now);

            return new EconomicIndicatorMaintenanceResult(true, $this->synchronize('MANUAL', $now));
        } finally {
            $lock->release();
        }
    }

    public function synchronizeScheduled(): ?MaintenanceExecutionHistory
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $lock = Cache::lock($this->lockKey(), config('economic-indicators.maintenance_lock_seconds'));

        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->synchronize('SCHEDULED', $now);
        } finally {
            $lock->release();
        }
    }

    private function recordAudit(User $performedByUser, string $outcome, CarbonImmutable $now): void
    {
        MaintenanceAdministrativeAudit::query()->create([
            'idPerformedByUser' => $performedByUser->id,
            'routineKey' => self::ROUTINE_KEY,
            'action' => 'SYNCHRONIZE',
            'outcome' => $outcome,
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
        $result = $this->synchronization->synchronize($now);

        $history->update([
            'state' => $result->succeeded ? 'SUCCEEDED' : 'FAILED',
            'startedAt' => $result->startedAt,
            'completedAt' => $result->finishedAt,
            'summary' => $result->succeeded
                ? "Created {$result->createdCount}; revised {$result->revisedCount}."
                : 'The official source could not be synchronized.',
            'details' => $result->succeeded
                ? [
                    'createdCount' => $result->createdCount,
                    'updatedCount' => $result->revisedCount,
                    'revisedCount' => $result->revisedCount,
                    'series' => $result->series,
                ]
                : ['failureCode' => $result->failureCode, 'series' => $result->series],
        ]);

        return $history;
    }

    private function lockKey(): string
    {
        return 'maintenance.'.self::ROUTINE_KEY;
    }
}
