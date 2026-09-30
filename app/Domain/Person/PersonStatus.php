<?php

namespace App\Domain\Person;

enum PersonStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
