<?php

namespace App\Services\Authorization;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationAuditRetentionService
{
    /**
     * Deletes only events that have completed the configured retention period.
     *
     * @throws LogicException when the configured retention is not a positive number of days.
     */
    public function purgeExpired(?CarbonInterface $now = null): int
    {
        $retentionDays = config('authorization.auditRetentionDays');
        if (! is_int($retentionDays) || $retentionDays < 1) {
            throw new LogicException('AUTHORIZATION_AUDIT_RETENTION_DAYS must be a positive integer.');
        }

        $expiresAt = ($now ?? now())->copy()->subDays($retentionDays);

        return DB::table('auth_audit_event')->where('occurredAt', '<=', $expiresAt)->delete();
    }
}
