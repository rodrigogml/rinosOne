<?php

namespace App\Services\Maintenance;

final readonly class MaintenanceRetentionResult
{
    public function __construct(
        public int $deletedExecutionHistoryCount,
        public int $deletedAdministrativeAuditCount,
    ) {}
}
