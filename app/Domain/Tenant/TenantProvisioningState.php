<?php

namespace App\Domain\Tenant;

enum TenantProvisioningState: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
