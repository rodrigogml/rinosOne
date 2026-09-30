<?php

namespace App\Services\Person;

readonly class PersonAuditRetentionResult
{
    public function __construct(
        public int $processedTenantCount,
        public int $deletedEventCount,
    ) {}
}
