<?php

namespace App\Domain\Tenant;

enum TenantState: string
{
    case Provisioning = 'PROVISIONING';
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Failed = 'FAILED';
}
