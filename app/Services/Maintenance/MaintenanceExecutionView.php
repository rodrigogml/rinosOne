<?php

namespace App\Services\Maintenance;

use Carbon\CarbonInterface;

/**
 * Safe technical execution data prepared for maintenance administration views.
 */
final readonly class MaintenanceExecutionView
{
    /** @param array<string, int|string|null> $details */
    public function __construct(
        public string $state,
        public string $triggerType,
        public CarbonInterface $startedAt,
        public ?CarbonInterface $completedAt,
        public ?string $summary,
        public ?int $createdCount,
        public ?int $updatedCount,
        public array $details,
    ) {}
}
