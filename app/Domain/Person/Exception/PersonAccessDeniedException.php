<?php

namespace App\Domain\Person\Exception;

use RuntimeException;

/**
 * Signals that a People operation cannot proceed in the requested tenant context.
 *
 * The exception intentionally contains no authorization or tenant details. HTTP
 * endpoints map it to the safe People error envelope defined by the API contract.
 */
class PersonAccessDeniedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Access to the requested People operation is denied.');
    }
}
