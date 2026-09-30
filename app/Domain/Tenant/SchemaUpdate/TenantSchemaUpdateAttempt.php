<?php

namespace App\Domain\Tenant\SchemaUpdate;

final readonly class TenantSchemaUpdateAttempt
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $targetCatalog,
        public int $attemptCount,
    ) {}
}
