<?php

namespace App\Domain\Profile\Exception;

use RuntimeException;

class AvatarProcessingUnavailableException extends RuntimeException
{
    public const ERROR_CODE = 'AVATAR_PROCESSING_UNAVAILABLE';

    /** @param list<string> $missingFunctions */
    public function __construct(public readonly array $missingFunctions)
    {
        parent::__construct('Avatar processing is temporarily unavailable.');
    }
}
