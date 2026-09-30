<?php

namespace App\Http\Controllers\Api\V1\Platform\Maintenance;

use App\Domain\Authorization\AuthorizationScope;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Maintenance\FinancialInstitutionMaintenanceService;
use App\Services\Maintenance\IbgeTerritoryMaintenanceService;
use App\Services\Maintenance\MaintenanceAdministrativeAuditView;
use App\Services\Maintenance\MaintenanceExecutionView;
use App\Services\Maintenance\MaintenanceHubService;
use App\Services\Maintenance\MaintenanceRoutineDetail;
use App\Services\Maintenance\PersonAuditRetentionMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provides the platform maintenance administration endpoints for explicitly integrated routines.
 */
class MaintenanceController extends Controller
{
    public function index(Request $request, MaintenanceHubService $hub, AuthorizationService $authorization): JsonResponse
    {
        return response()->json([
            'routines' => array_map(
                fn (MaintenanceRoutineDetail $routine): array => $this->summary($routine, $request->user(), $authorization),
                $hub->listKnownRoutines($request->user()),
            ),
        ]);
    }

    public function show(string $routineKey, Request $request, MaintenanceHubService $hub, AuthorizationService $authorization): JsonResponse
    {
        $routine = $this->routine($routineKey, $request->user(), $hub);

        if ($routine === null) {
            return $this->notAvailable();
        }

        return response()->json([
            'routine' => [
                ...$this->summary($routine, $request->user(), $authorization),
                'lastExecution' => $routine->lastExecution === null ? null : $this->execution($routine->lastExecution),
                'executionHistory' => array_map($this->execution(...), $routine->executionHistory),
                'administrativeAudits' => array_map($this->administrativeAudit(...), $routine->administrativeAudits),
            ],
        ]);
    }

    public function audit(Request $request, MaintenanceHubService $hub): JsonResponse
    {
        $routine = $hub->financialInstitutionCatalog($request->user(), auditLimit: $this->limit($request));

        if ($routine === null) {
            return $this->notAvailable();
        }

        return response()->json([
            'administrativeAudits' => array_map($this->administrativeAudit(...), $routine->administrativeAudits),
        ]);
    }

    public function synchronize(string $routineKey, string $action, Request $request, MaintenanceHubService $hub): JsonResponse
    {
        if ($routineKey !== FinancialInstitutionMaintenanceService::ROUTINE_KEY || $action !== 'SYNCHRONIZE') {
            return $this->notAvailable();
        }

        $result = $hub->requestFinancialInstitutionSynchronization($request->user());

        if (! $result->accepted) {
            return response()->json([
                'error' => [
                    'code' => $result->refusalCode === 'NOT_AUTHORIZED' ? 'MAINTENANCE_ACTION_NOT_ALLOWED' : 'MAINTENANCE_ALREADY_RUNNING',
                    'message' => $result->refusalCode === 'NOT_AUTHORIZED'
                        ? 'Ação de manutenção não permitida.'
                        : 'A rotina de manutenção já está em execução.',
                ],
            ], $result->refusalCode === 'NOT_AUTHORIZED' ? 403 : 409);
        }

        return response()->json([
            'execution' => $this->executionFromHistory($result->executionHistory),
        ]);
    }

    private function routine(string $routineKey, User $principal, MaintenanceHubService $hub): ?MaintenanceRoutineDetail
    {
        return match ($routineKey) {
            FinancialInstitutionMaintenanceService::ROUTINE_KEY => $hub->financialInstitutionCatalog($principal),
            IbgeTerritoryMaintenanceService::ROUTINE_KEY => $hub->ibgeTerritoryCatalog($principal),
            PersonAuditRetentionMaintenanceService::ROUTINE_KEY => $hub->personAuditRetentionCatalog($principal),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function summary(MaintenanceRoutineDetail $routine, User $principal, AuthorizationService $authorization): array
    {
        return [
            'routineKey' => $routine->routineKey,
            'title' => $routine->title,
            'description' => $routine->description,
            'state' => $routine->state,
            'scheduleDescription' => $routine->scheduleDescription,
            'capabilities' => [
                'canSynchronize' => $routine->supportsManualSynchronization && $authorization->check(
                    $principal,
                    FinancialInstitutionMaintenanceService::SYNCHRONIZE_PERMISSION_KEY,
                    AuthorizationScope::Platform,
                )->allowed,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function execution(MaintenanceExecutionView $execution): array
    {
        return [
            'state' => $execution->state,
            'triggerType' => $execution->triggerType,
            'startedAt' => $execution->startedAt->toIso8601String(),
            'completedAt' => $execution->completedAt?->toIso8601String(),
            'summary' => $execution->summary,
            'createdCount' => $execution->createdCount,
            'updatedCount' => $execution->updatedCount,
            'details' => $execution->details,
        ];
    }

    /** @return array<string, mixed> */
    private function executionFromHistory(?MaintenanceExecutionHistory $execution): array
    {
        return $this->execution(new MaintenanceExecutionView(
            state: $execution->state,
            triggerType: $execution->triggerType,
            startedAt: $execution->startedAt,
            completedAt: $execution->completedAt,
            summary: $execution->summary,
            createdCount: $execution->details['createdCount'] ?? null,
            updatedCount: $execution->details['updatedCount'] ?? null,
            details: $execution->details ?? [],
        ));
    }

    /** @return array<string, mixed> */
    private function administrativeAudit(MaintenanceAdministrativeAuditView $audit): array
    {
        return [
            'performedByUserId' => $audit->performedByUserId,
            'action' => $audit->action,
            'outcome' => $audit->outcome,
            'occurredAt' => $audit->occurredAt->toIso8601String(),
        ];
    }

    private function limit(Request $request): int
    {
        $limit = $request->integer('limit', 20);

        return min(100, max(1, $limit));
    }

    private function notAvailable(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'MAINTENANCE_ROUTINE_NOT_AVAILABLE', 'message' => 'Rotina de manutenção não disponível.']], 404);
    }
}
