<?php

namespace App\Infrastructure\Tenant;

use InvalidArgumentException;
use LogicException;

class TenantSchemaName
{
    public function fromTenantId(string $tenantId): string
    {
        if (preg_match('/\A[1-9][0-9]{0,19}\z/', $tenantId) !== 1) {
            throw new InvalidArgumentException('The tenant identifier must be a positive unsigned integer.');
        }

        $prefix = (string) config('access.schemas.tenantPrefix');

        if (! preg_match('/\A[a-z][a-z0-9_]*_\z/', $prefix)) {
            throw new LogicException('The tenant schema prefix configuration is invalid.');
        }

        $schema = $prefix.$tenantId;

        if (strlen($schema) > 64 || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $schema)) {
            throw new LogicException('The derived tenant schema name is invalid.');
        }

        return $schema;
    }
}
