<?php

namespace App\Domain\Authorization;

enum AuthorizationScope: string
{
    case Platform = 'PLATFORM';
    case Personal = 'PERSONAL';
    case Tenant = 'TENANT';
}
