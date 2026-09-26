<?php

namespace App\Domain\Authorization;

enum AuthorizationRoleType: string
{
    case System = 'SYSTEM';
    case Builtin = 'BUILTIN';
    case Custom = 'CUSTOM';
}
