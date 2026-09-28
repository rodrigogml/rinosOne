<?php

namespace App\Domain\FileStorage\Exception;

use LogicException;

/** Signals a safe, client-addressable refusal of a Drive workspace command. */
class DriveWorkspaceCommandException extends LogicException
{
    public function __construct(public readonly string $reasonCode)
    {
        parent::__construct($reasonCode);
    }
}
