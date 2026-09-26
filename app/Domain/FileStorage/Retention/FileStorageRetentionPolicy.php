<?php

namespace App\Domain\FileStorage\Retention;

use Carbon\CarbonImmutable;

class FileStorageRetentionPolicy
{
    /**
     * Determines the deadline after which a trashed possession may be released.
     */
    public function purgeAfter(?CarbonImmutable $from = null): CarbonImmutable
    {
        return ($from ?? now()->toImmutable())->addDays((int) config('file-storage.retention.trashDays'));
    }

    /**
     * Determines the minimum technical retention for bytes that have lost a live possession reference.
     */
    public function retentionUntil(?CarbonImmutable $from = null): CarbonImmutable
    {
        $days = max(
            (int) config('file-storage.retention.backupDays'),
            (int) config('file-storage.retention.technicalDays'),
        );

        return ($from ?? now()->toImmutable())->addDays($days);
    }

    /**
     * Determines the oldest creation time eligible for unreferenced version cleanup.
     */
    public function versionEligibleSince(?CarbonImmutable $from = null): CarbonImmutable
    {
        return ($from ?? now()->toImmutable())->subDays(max(
            (int) config('file-storage.retention.backupDays'),
            (int) config('file-storage.retention.technicalDays'),
        ));
    }

    /**
     * Determines the oldest moment at which an untracked physical object can be reconciled as an orphan.
     */
    public function orphanEligibleSince(?CarbonImmutable $from = null): CarbonImmutable
    {
        return ($from ?? now()->toImmutable())->subDays((int) config('file-storage.retention.orphanDays'));
    }
}
