<?php

namespace App\Domain\Authorization;

enum AuthorizationAssignmentState: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
