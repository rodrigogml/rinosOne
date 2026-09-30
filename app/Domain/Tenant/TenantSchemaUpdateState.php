<?php

namespace App\Domain\Tenant;

enum TenantSchemaUpdateState: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
