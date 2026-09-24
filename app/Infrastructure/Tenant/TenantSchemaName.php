<?php

namespace App\Infrastructure\Tenant;

use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class TenantSchemaName
{
    public function fromTenantId(string $tenantId): string
    {
        if (! Str::isUlid($tenantId)) {
            throw new InvalidArgumentException('The tenant identifier must be a valid ULID.');
        }

        $prefix = (string) config('access.schemas.tenantPrefix');

        if (! preg_match('/\A[a-z][a-z0-9_]*_\z/', $prefix)) {
            throw new LogicException('The tenant schema prefix configuration is invalid.');
        }

        $schema = $prefix.strtolower($tenantId);

        if (strlen($schema) > 64 || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $schema)) {
            throw new LogicException('The derived tenant schema name is invalid.');
        }

        return $schema;
    }
}
