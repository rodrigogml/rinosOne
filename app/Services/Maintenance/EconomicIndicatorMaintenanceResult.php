<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceExecutionHistory;

final readonly class EconomicIndicatorMaintenanceResult
{
    public function __construct(
        public bool $accepted,
        public ?MaintenanceExecutionHistory $executionHistory,
        public ?string $refusalCode = null,
    ) {}
}
