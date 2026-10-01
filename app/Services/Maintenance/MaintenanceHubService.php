<?php

namespace App\Services\Maintenance;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\MaintenanceAdministrativeAudit;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Carbon\CarbonImmutable;

/**
 * Provides maintenance-administration read models for routines explicitly integrated with the hub.
 *
 * Each routine is composed directly here; this service deliberately does not define a registration,
 * discovery, or plug-in contract for maintenance routines.
 */
final class MaintenanceHubService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly FinancialInstitutionMaintenanceService $financialInstitutionMaintenance,
        private readonly EconomicIndicatorMaintenanceService $economicIndicatorMaintenance,
        private readonly IbgeTerritoryMaintenanceService $ibgeTerritoryMaintenance,
        private readonly PersonAuditRetentionMaintenanceService $personAuditRetention,
    ) {}

    /**
     * @return list<MaintenanceRoutineDetail>
     */
    public function listKnownRoutines(User $principal): array
    {
        return array_values(array_filter([
            $this->financialInstitutionCatalog($principal),
            $this->economicIndicators($principal),
            $this->ibgeTerritoryCatalog($principal),
            $this->personAuditRetentionCatalog($principal),
        ]));
    }

    public function financialInstitutionCatalog(User $principal, int $historyLimit = 20, int $auditLimit = 20): ?MaintenanceRoutineDetail
    {
        if (! $this->canReadFinancialInstitutionCatalog($principal)) {
            return null;
        }

        $historyLimit = $this->validatedLimit($historyLimit);
        $auditLimit = $this->validatedLimit($auditLimit);
        $executionHistory = MaintenanceExecutionHistory::query()
            ->where('routineKey', FinancialInstitutionMaintenanceService::ROUTINE_KEY)
            ->orderByDesc('startedAt')
            ->limit($historyLimit)
            ->get();
        $administrativeAudits = MaintenanceAdministrativeAudit::query()
            ->where('routineKey', FinancialInstitutionMaintenanceService::ROUTINE_KEY)
            ->orderByDesc('occurredAt')
            ->limit($auditLimit)
            ->get();
        $lastExecution = $executionHistory->first();

        return new MaintenanceRoutineDetail(
            routineKey: FinancialInstitutionMaintenanceService::ROUTINE_KEY,
            title: 'Instituições financeiras',
            description: 'Atualiza o catálogo global a partir da fonte oficial do Banco Central do Brasil.',
            state: $lastExecution === null ? 'NOT_EXECUTED' : ($lastExecution->completedAt === null ? 'RUNNING' : $lastExecution->state),
            scheduleDescription: 'Diária',
            supportsManualSynchronization: true,
            lastExecution: $lastExecution === null ? null : $this->executionView($lastExecution),
            executionHistory: $executionHistory->map(fn (MaintenanceExecutionHistory $execution): MaintenanceExecutionView => $this->executionView($execution))->all(),
            administrativeAudits: $administrativeAudits->map(fn (MaintenanceAdministrativeAudit $audit): MaintenanceAdministrativeAuditView => new MaintenanceAdministrativeAuditView(
                performedByUserId: $audit->idPerformedByUser,
                action: $audit->action,
                outcome: $audit->outcome,
                occurredAt: $audit->occurredAt,
            ))->all(),
        );
    }

    public function ibgeTerritoryCatalog(User $principal, int $historyLimit = 20): ?MaintenanceRoutineDetail
    {
        if (! $this->canReadIbgeTerritoryCatalog($principal)) {
            return null;
        }

        $historyLimit = $this->validatedLimit($historyLimit);
        $executionHistory = MaintenanceExecutionHistory::query()
            ->where('routineKey', IbgeTerritoryMaintenanceService::ROUTINE_KEY)
            ->orderByDesc('startedAt')
            ->limit($historyLimit)
            ->get();
        $lastExecution = $executionHistory->first();

        return new MaintenanceRoutineDetail(
            routineKey: IbgeTerritoryMaintenanceService::ROUTINE_KEY,
            title: 'Localidades brasileiras',
            description: 'Atualiza o catálogo territorial global com dados oficiais do IBGE.',
            state: $lastExecution === null ? 'NOT_EXECUTED' : ($lastExecution->completedAt === null ? 'RUNNING' : $lastExecution->state),
            scheduleDescription: 'Inicial automática e mensal',
            supportsManualSynchronization: false,
            lastExecution: $lastExecution === null ? null : $this->executionView($lastExecution),
            executionHistory: $executionHistory->map(fn (MaintenanceExecutionHistory $execution): MaintenanceExecutionView => $this->executionView($execution))->all(),
            administrativeAudits: [],
        );
    }

    public function economicIndicators(User $principal, int $historyLimit = 20, int $auditLimit = 20): ?MaintenanceRoutineDetail
    {
        if (! $this->canReadEconomicIndicators($principal)) {
            return null;
        }

        $executionHistory = MaintenanceExecutionHistory::query()
            ->where('routineKey', EconomicIndicatorMaintenanceService::ROUTINE_KEY)
            ->orderByDesc('startedAt')
            ->limit($this->validatedLimit($historyLimit))
            ->get();
        $administrativeAudits = MaintenanceAdministrativeAudit::query()
            ->where('routineKey', EconomicIndicatorMaintenanceService::ROUTINE_KEY)
            ->orderByDesc('occurredAt')
            ->limit($this->validatedLimit($auditLimit))
            ->get();
        $lastExecution = $executionHistory->first();

        return new MaintenanceRoutineDetail(
            routineKey: EconomicIndicatorMaintenanceService::ROUTINE_KEY,
            title: 'Indicadores econômicos',
            description: 'Atualiza séries econômicas globais e cotações PTAX oficiais do Banco Central do Brasil.',
            state: $lastExecution === null ? 'NOT_EXECUTED' : ($lastExecution->completedAt === null ? 'RUNNING' : $lastExecution->state),
            scheduleDescription: 'Diária',
            supportsManualSynchronization: true,
            lastExecution: $lastExecution === null ? null : $this->executionView($lastExecution),
            executionHistory: $executionHistory->map(fn (MaintenanceExecutionHistory $execution): MaintenanceExecutionView => $this->executionView($execution))->all(),
            administrativeAudits: $administrativeAudits->map(fn (MaintenanceAdministrativeAudit $audit): MaintenanceAdministrativeAuditView => new MaintenanceAdministrativeAuditView(
                performedByUserId: $audit->idPerformedByUser,
                action: $audit->action,
                outcome: $audit->outcome,
                occurredAt: $audit->occurredAt,
            ))->all(),
        );
    }

    public function personAuditRetentionCatalog(User $principal, int $historyLimit = 20): ?MaintenanceRoutineDetail
    {
        if (! $this->authorization->check($principal, PersonAuditRetentionMaintenanceService::READ_PERMISSION_KEY, AuthorizationScope::Platform)->allowed) {
            return null;
        }
        $executionHistory = MaintenanceExecutionHistory::query()->where('routineKey', PersonAuditRetentionMaintenanceService::ROUTINE_KEY)->orderByDesc('startedAt')->limit($this->validatedLimit($historyLimit))->get();
        $lastExecution = $executionHistory->first();

        return new MaintenanceRoutineDetail(
            routineKey: PersonAuditRetentionMaintenanceService::ROUTINE_KEY,
            title: 'Auditoria de Pessoas',
            description: 'Remove eventos vencidos conforme a política de retenção configurada.',
            state: $lastExecution === null ? 'NOT_EXECUTED' : ($lastExecution->completedAt === null ? 'RUNNING' : $lastExecution->state),
            scheduleDescription: 'Diária',
            supportsManualSynchronization: false,
            lastExecution: $lastExecution === null ? null : $this->executionView($lastExecution),
            executionHistory: $executionHistory->map(fn (MaintenanceExecutionHistory $execution): MaintenanceExecutionView => $this->executionView($execution))->all(),
            administrativeAudits: [],
        );
    }

    /**
     * Requests the manual synchronization exposed by the financial-institution integration.
     *
     * Authorization is intentionally enforced by the future administrative transport before it
     * reaches this operation; the financial routine records the accepted or refused request.
     */
    public function requestFinancialInstitutionSynchronization(User $performedByUser): FinancialInstitutionMaintenanceResult
    {
        if (! $this->authorization->check(
            $performedByUser,
            FinancialInstitutionMaintenanceService::SYNCHRONIZE_PERMISSION_KEY,
            AuthorizationScope::Platform,
        )->allowed) {
            $now = CarbonImmutable::now('America/Sao_Paulo');
            MaintenanceAdministrativeAudit::query()->create([
                'idPerformedByUser' => $performedByUser->id,
                'routineKey' => FinancialInstitutionMaintenanceService::ROUTINE_KEY,
                'action' => 'SYNCHRONIZE',
                'outcome' => 'REFUSED_NOT_AUTHORIZED',
                'occurredAt' => $now,
                'expiresAt' => $now->addDays(config('maintenance.administrativeAuditRetentionDays')),
            ]);

            return new FinancialInstitutionMaintenanceResult(false, null, 'NOT_AUTHORIZED');
        }

        return $this->financialInstitutionMaintenance->synchronizeManually($performedByUser);
    }

    public function requestEconomicIndicatorSynchronization(User $performedByUser): EconomicIndicatorMaintenanceResult
    {
        if (! $this->authorization->check(
            $performedByUser,
            EconomicIndicatorMaintenanceService::SYNCHRONIZE_PERMISSION_KEY,
            AuthorizationScope::Platform,
        )->allowed) {
            $now = CarbonImmutable::now('America/Sao_Paulo');
            MaintenanceAdministrativeAudit::query()->create([
                'idPerformedByUser' => $performedByUser->id,
                'routineKey' => EconomicIndicatorMaintenanceService::ROUTINE_KEY,
                'action' => 'SYNCHRONIZE',
                'outcome' => 'REFUSED_NOT_AUTHORIZED',
                'occurredAt' => $now,
                'expiresAt' => $now->addDays(config('maintenance.administrativeAuditRetentionDays')),
            ]);

            return new EconomicIndicatorMaintenanceResult(false, null, 'NOT_AUTHORIZED');
        }

        return $this->economicIndicatorMaintenance->synchronizeManually($performedByUser);
    }

    private function canReadFinancialInstitutionCatalog(User $principal): bool
    {
        return $this->authorization->check(
            $principal,
            FinancialInstitutionMaintenanceService::READ_PERMISSION_KEY,
            AuthorizationScope::Platform,
        )->allowed;
    }

    private function canReadIbgeTerritoryCatalog(User $principal): bool
    {
        return $this->authorization->check(
            $principal,
            IbgeTerritoryMaintenanceService::READ_PERMISSION_KEY,
            AuthorizationScope::Platform,
        )->allowed;
    }

    private function canReadEconomicIndicators(User $principal): bool
    {
        return $this->authorization->check(
            $principal,
            EconomicIndicatorMaintenanceService::READ_PERMISSION_KEY,
            AuthorizationScope::Platform,
        )->allowed;
    }

    private function executionView(MaintenanceExecutionHistory $execution): MaintenanceExecutionView
    {
        $details = $execution->details;

        return new MaintenanceExecutionView(
            state: $execution->completedAt === null ? 'RUNNING' : $execution->state,
            triggerType: $execution->triggerType,
            startedAt: $execution->startedAt,
            completedAt: $execution->completedAt,
            summary: $execution->summary,
            createdCount: isset($details['createdCount']) ? (int) $details['createdCount'] : null,
            updatedCount: isset($details['updatedCount']) ? (int) $details['updatedCount'] : null,
            details: $this->safeDetails($details),
        );
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @return array<string, mixed>
     */
    private function safeDetails(?array $details): array
    {
        $safeKeys = [
            'createdCount',
            'updatedCount',
            'revisedCount',
            'createdCountryCount',
            'updatedCountryCount',
            'createdStateCount',
            'updatedStateCount',
            'createdMunicipalityCount',
            'updatedMunicipalityCount',
            'failureCode',
        ];

        $safeDetails = array_filter(
            array_intersect_key($details ?? [], array_flip($safeKeys)),
            static fn (mixed $value): bool => is_int($value) || is_string($value) || $value === null,
        );
        $series = $details['series'] ?? null;
        if (! is_array($series)) {
            return $safeDetails;
        }

        $safeSeries = [];
        foreach ($series as $code => $result) {
            if (! is_string($code) || ! is_array($result)) {
                continue;
            }
            $safeResult = array_filter(
                array_intersect_key($result, array_flip([
                    'sourceKey',
                    'from',
                    'through',
                    'createdCount',
                    'revisedCount',
                    'recalculatedCount',
                    'failureCode',
                ])),
                static fn (mixed $value): bool => is_int($value) || is_string($value),
            );
            if ($safeResult !== []) {
                $safeSeries[$code] = $safeResult;
            }
        }

        return $safeSeries === [] ? $safeDetails : $safeDetails + ['series' => $safeSeries];
    }

    private function validatedLimit(int $limit): int
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Maintenance history limits must be between 1 and 100.');
        }

        return $limit;
    }
}
