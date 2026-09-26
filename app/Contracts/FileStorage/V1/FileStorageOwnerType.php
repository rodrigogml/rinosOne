<?php

namespace App\Contracts\FileStorage\V1;

enum FileStorageOwnerType: string
{
    case User = 'USER';
    case Tenant = 'TENANT';
}
