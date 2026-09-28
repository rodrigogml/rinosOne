<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceExecutionHistory;
use App\Services\Locality\IbgeTerritorySynchronizationResult;
use App\Services\Locality\IbgeTerritorySynchronizationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

final class IbgeTerritoryMaintenanceService
{
    public const ROUTINE_KEY = 'locality-ibge-territory-catalog';

    public const READ_PERMISSION_KEY = 'platform.maintenance.locality-ibge.read';

    public function __construct(private readonly IbgeTerritorySynchronizationService $synchronization) {}

    /**
     * Start the automatic synchronization only when it is due under its own policy.
     * A concurrent evaluator is refused without creating another execution history.
     */
    public function synchronizeWhenDue(?CarbonInterface $now = null): ?MaintenanceExecutionHistory
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now('America/Sao_Paulo'));
        $lock = Cache::lock($this->lockKey(), $this->lockSeconds());

        if (! $lock->get()) {
            return null;
        }

        try {
            if (! $this->isDue($now)) {
                return null;
            }

            return $this->synchronize($now);
        } finally {
            $lock->release();
        }
    }

    private function isDue(CarbonImmutable $now): bool
    {
        $lastExecution = MaintenanceExecutionHistory::query()
            ->where('routineKey', self::ROUTINE_KEY)
            ->whereNotNull('completedAt')
            ->orderByDesc('completedAt')
            ->orderByDesc('id')
            ->first();

        if ($lastExecution === null) {
            return true;
        }

        $completedAt = CarbonImmutable::parse(
            $lastExecution->getRawOriginal('completedAt'),
            $now->getTimezone(),
        );

        if ($lastExecution->state === 'FAILED') {
            return $completedAt->lessThanOrEqualTo($now->subHours($this->failureRetryDelayHours()));
        }

        if ($lastExecution->state === 'SUCCEEDED') {
            return $completedAt->lessThanOrEqualTo($now->subMonthsNoOverflow($this->successIntervalMonths()));
        }

        return false;
    }

    private function synchronize(CarbonImmutable $now): MaintenanceExecutionHistory
    {
        $history = MaintenanceExecutionHistory::query()->create([
            'routineKey' => self::ROUTINE_KEY,
            'triggerType' => 'SCHEDULED',
            'state' => 'RUNNING',
            'startedAt' => $now,
            'expiresAt' => $now->addDays(config('maintenance.historyRetentionDays')),
        ]);
        $result = $this->synchronization->synchronize();

        $history->update($this->resultAttributes($result));

        return $history;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultAttributes(IbgeTerritorySynchronizationResult $result): array
    {
        if (! $result->succeeded) {
            return [
                'state' => 'FAILED',
                'startedAt' => $result->startedAt,
                'completedAt' => $result->finishedAt,
                'summary' => 'Não foi possível sincronizar a fonte oficial.',
                'details' => ['failureCode' => $result->failureCode],
            ];
        }

        return [
            'state' => 'SUCCEEDED',
            'startedAt' => $result->startedAt,
            'completedAt' => $result->finishedAt,
            'summary' => 'Catálogo territorial atualizado.',
            'details' => [
                'createdCountryCount' => $result->createdCountryCount,
                'updatedCountryCount' => $result->updatedCountryCount,
                'createdStateCount' => $result->createdStateCount,
                'updatedStateCount' => $result->updatedStateCount,
                'createdMunicipalityCount' => $result->createdMunicipalityCount,
                'updatedMunicipalityCount' => $result->updatedMunicipalityCount,
            ],
        ];
    }

    private function lockKey(): string
    {
        return 'maintenance.'.self::ROUTINE_KEY;
    }

    private function lockSeconds(): int
    {
        return max(1, (int) config('localities.ibge_territory_maintenance.lock_seconds'));
    }

    private function successIntervalMonths(): int
    {
        return max(1, (int) config('localities.ibge_territory_maintenance.success_interval_months'));
    }

    private function failureRetryDelayHours(): int
    {
        return max(1, (int) config('localities.ibge_territory_maintenance.failure_retry_delay_hours'));
    }
}
