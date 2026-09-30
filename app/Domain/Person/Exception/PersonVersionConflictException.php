<?php

namespace App\Domain\Person\Exception;

use RuntimeException;

class PersonVersionConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The Person has changed since it was read.');
    }
}
