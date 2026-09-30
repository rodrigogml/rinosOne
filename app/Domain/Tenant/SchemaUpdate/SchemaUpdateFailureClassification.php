<?php

namespace App\Domain\Tenant\SchemaUpdate;

enum SchemaUpdateFailureClassification: string
{
    case Transient = 'TRANSIENT';
    case Terminal = 'TERMINAL';
}
