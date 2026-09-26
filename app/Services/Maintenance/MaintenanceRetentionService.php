<?php

namespace App\Services\Maintenance;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class MaintenanceRetentionService
{
    public function purgeExpired(?CarbonInterface $now = null): MaintenanceRetentionResult
    {
        $now ??= now();

        return new MaintenanceRetentionResult(
            deletedExecutionHistoryCount: DB::table('maintenanceExecutionHistory')
                ->where('expiresAt', '<=', $now)
                ->delete(),
            deletedAdministrativeAuditCount: DB::table('maintenanceAdministrativeAudit')
                ->where('expiresAt', '<=', $now)
                ->delete(),
        );
    }
}
