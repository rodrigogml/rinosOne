<?php

namespace App\Domain\Tenant\SchemaUpdate;

use InvalidArgumentException;

final readonly class TenantSchemaUpdateFailure
{
    public function __construct(
        public SchemaUpdateFailureClassification $classification,
        public string $code,
    ) {
        if (! preg_match('/^TENANT_SCHEMA_UPDATE_[A-Z0-9_]+$/', $code)) {
            throw new InvalidArgumentException('The tenant schema update failure code is invalid.');
        }
    }
}
