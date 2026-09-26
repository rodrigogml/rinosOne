<?php

namespace App\Domain\FileStorage\Compression;

class PreparedFileStorageRepresentation
{
    public function __construct(
        public readonly string $encoding,
        public readonly string $stagingKey,
        public readonly string $storedSha256,
        public readonly int $storedSizeBytes,
        public readonly bool $compressed,
    ) {}
}
