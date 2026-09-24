<?php

namespace App\Domain\Tenant;

enum TenantMembershipRole: string
{
    case Owner = 'OWNER';
}
