<?php

namespace App\Domain\Tenant\Exception;

use RuntimeException;

class TenantSchemaUnavailableException extends RuntimeException
{
    public const ERROR_CODE = 'TENANT_SCHEMA_UNAVAILABLE';
}
