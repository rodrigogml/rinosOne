<?php

namespace App\Domain\FileStorage\Exception;

use LogicException;

class DriveWorkspaceProjectionException extends LogicException
{
    public function __construct(public readonly string $reasonCode)
    {
        parent::__construct($reasonCode);
    }
}
