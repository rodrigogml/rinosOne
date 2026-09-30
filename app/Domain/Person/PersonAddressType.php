<?php

namespace App\Domain\Person;

enum PersonAddressType: string
{
    case RESIDENTIAL = 'RESIDENTIAL';
    case COMMERCIAL = 'COMMERCIAL';
    case BILLING = 'BILLING';
    case DELIVERY = 'DELIVERY';
    case BRANCH = 'BRANCH';
    case OTHER = 'OTHER';
}
