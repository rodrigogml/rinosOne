<?php

namespace App\Domain\Profile\Avatar;

class ProcessedAvatar
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int $width,
        public readonly int $height,
    ) {}
}
