<?php

namespace App\Services\Maintenance;

use Carbon\CarbonInterface;

/**
 * Safe administrative-audit data prepared for maintenance administration views.
 */
final readonly class MaintenanceAdministrativeAuditView
{
    public function __construct(
        public int $performedByUserId,
        public string $action,
        public string $outcome,
        public CarbonInterface $occurredAt,
    ) {}
}
