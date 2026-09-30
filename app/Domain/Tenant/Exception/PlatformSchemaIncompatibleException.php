<?php

namespace App\Domain\Tenant\Exception;

use RuntimeException;

class PlatformSchemaIncompatibleException extends RuntimeException
{
    public const ERROR_CODE = 'PLATFORM_SCHEMA_INCOMPATIBLE';
}
