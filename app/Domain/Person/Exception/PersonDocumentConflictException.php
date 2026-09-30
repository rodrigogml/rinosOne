<?php

namespace App\Domain\Person\Exception;

use RuntimeException;

class PersonDocumentConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A Person with this document already exists in the tenant.');
    }
}
