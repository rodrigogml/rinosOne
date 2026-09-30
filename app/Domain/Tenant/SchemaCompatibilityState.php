<?php

namespace App\Domain\Tenant;

enum SchemaCompatibilityState: string
{
    case Compatible = 'COMPATIBLE';
    case Incompatible = 'INCOMPATIBLE';
}
