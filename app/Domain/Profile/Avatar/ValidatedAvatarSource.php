<?php

namespace App\Domain\Profile\Avatar;

class ValidatedAvatarSource
{
    public function __construct(
        public readonly string $path,
        public readonly string $mimeType,
        public readonly int $width,
        public readonly int $height,
    ) {}
}
