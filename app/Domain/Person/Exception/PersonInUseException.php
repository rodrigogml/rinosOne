<?php

namespace App\Domain\Person\Exception;

use App\Domain\Person\Deletion\PersonUsage;
use RuntimeException;

class PersonInUseException extends RuntimeException
{
    /** @param list<PersonUsage> $usages */
    public function __construct(public readonly array $usages)
    {
        parent::__construct('The Person is used by another module.');
    }
}
