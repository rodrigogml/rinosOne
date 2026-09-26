<?php

namespace App\Console\Commands;

use App\Services\Authorization\AuthorizationAuditRetentionService;
use Illuminate\Console\Command;
use Throwable;

class PurgeExpiredAuthorizationAuditEvents extends Command
{
    protected $signature = 'authorization:purge-audit-events';

    protected $description = 'Remove authorization audit events whose configured retention period has expired.';

    public function handle(AuthorizationAuditRetentionService $retention): int
    {
        try {
            $count = $retention->purgeExpired();
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Authorization audit retention did not run safely.');

            return self::FAILURE;
        }

        $this->components->info("{$count} expired authorization audit event(s) removed.");

        return self::SUCCESS;
    }
}
