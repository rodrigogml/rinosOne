<?php

namespace App\Domain\Tenant\Provisioning;

enum ProvisioningFailureClassification: string
{
    case TRANSIENT = 'TRANSIENT';
    case TERMINAL = 'TERMINAL';
}
