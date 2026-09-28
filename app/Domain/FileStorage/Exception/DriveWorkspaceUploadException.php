<?php

namespace App\Domain\FileStorage\Exception;

use LogicException;

class DriveWorkspaceUploadException extends LogicException
{
    public function __construct(public readonly string $reasonCode, ?\Throwable $previous = null)
    {
        parent::__construct($reasonCode, previous: $previous);
    }
}
