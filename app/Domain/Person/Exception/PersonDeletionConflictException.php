<?php

namespace App\Domain\Person\Exception;

use RuntimeException;

class PersonDeletionConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The Person cannot be deleted because it is still in use.');
    }
}
