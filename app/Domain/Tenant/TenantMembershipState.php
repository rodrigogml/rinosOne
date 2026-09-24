<?php

namespace App\Domain\Tenant;

enum TenantMembershipState: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
