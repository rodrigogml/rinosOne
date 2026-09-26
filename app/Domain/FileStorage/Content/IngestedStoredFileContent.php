<?php

namespace App\Domain\FileStorage\Content;

class IngestedStoredFileContent
{
    public function __construct(
        public readonly int $contentId,
        public readonly int $storageObjectId,
        public readonly string $logicalSha256,
        public readonly int $logicalSizeBytes,
        public readonly string $detectedMimeType,
        public readonly ?string $declaredExtension,
        public readonly string $storageKey,
        public readonly bool $reusedStorageObject,
    ) {}
}
