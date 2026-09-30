<?php

namespace App\Domain\Person\Exception;

use RuntimeException;

class PersonValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The Person identity data is invalid.');
    }
}
