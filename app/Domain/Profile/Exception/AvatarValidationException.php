<?php

namespace App\Domain\Profile\Exception;

use RuntimeException;

class AvatarValidationException extends RuntimeException
{
    public const FORMAT_UNSUPPORTED = 'AVATAR_FORMAT_UNSUPPORTED';

    public const TOO_LARGE = 'AVATAR_TOO_LARGE';

    public const DIMENSIONS_TOO_SMALL = 'AVATAR_DIMENSIONS_TOO_SMALL';

    public const CROP_INVALID = 'AVATAR_CROP_INVALID';

    public function __construct(public readonly string $errorCode)
    {
        parent::__construct('The avatar image could not be validated.');
    }
}
