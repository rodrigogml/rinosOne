<?php

namespace App\Services\Maintenance;

/**
 * Read model for one explicitly known maintenance routine.
 *
 * This DTO is a presentation result and does not define a plug-in contract for routines.
 */
final readonly class MaintenanceRoutineDetail
{
    /**
     * @param  list<MaintenanceExecutionView>  $executionHistory
     * @param  list<MaintenanceAdministrativeAuditView>  $administrativeAudits
     */
    public function __construct(
        public string $routineKey,
        public string $title,
        public string $description,
        public string $state,
        public string $scheduleDescription,
        public bool $supportsManualSynchronization,
        public ?MaintenanceExecutionView $lastExecution,
        public array $executionHistory,
        public array $administrativeAudits,
    ) {}
}
