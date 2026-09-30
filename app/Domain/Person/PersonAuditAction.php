<?php

namespace App\Domain\Person;

enum PersonAuditAction: string
{
    case CREATED = 'CREATED';
    case UPDATED = 'UPDATED';
    case INACTIVATED = 'INACTIVATED';
    case REACTIVATED = 'REACTIVATED';
    case DELETED = 'DELETED';
}
