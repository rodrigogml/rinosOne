<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceExecutionHistory;
use App\Services\Person\PersonAuditRetentionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class PersonAuditRetentionMaintenanceService
{
    public const ROUTINE_KEY = 'person-audit-retention';

    public const READ_PERMISSION_KEY = 'platform.maintenance.person-audit-retention.read';

    public function __construct(private readonly PersonAuditRetentionService $retention) {}

    public function purgeScheduled(?CarbonInterface $now = null): ?MaintenanceExecutionHistory
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now('America/Sao_Paulo'));
        $lock = Cache::lock('maintenance.'.self::ROUTINE_KEY, 3600);
        if (! $lock->get()) {
            return null;
        }

        try {
            $history = MaintenanceExecutionHistory::query()->create([
                'routineKey' => self::ROUTINE_KEY,
                'triggerType' => 'SCHEDULED',
                'state' => 'RUNNING',
                'startedAt' => $now,
                'expiresAt' => $now->addDays(config('maintenance.historyRetentionDays')),
            ]);

            try {
                $result = $this->retention->purgeExpired($now);
                $history->update([
                    'state' => 'SUCCEEDED',
                    'completedAt' => $now,
                    'summary' => 'Auditoria de Pessoas retida conforme a política configurada.',
                    'details' => [
                        'processedTenantCount' => $result->processedTenantCount,
                        'deletedEventCount' => $result->deletedEventCount,
                    ],
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $history->update([
                    'state' => 'FAILED',
                    'completedAt' => $now,
                    'summary' => 'Não foi possível aplicar a retenção da auditoria de Pessoas.',
                    'details' => ['failureCode' => 'PERSON_AUDIT_RETENTION_FAILED'],
                ]);
            }

            return $history;
        } finally {
            $lock->release();
        }
    }
}
