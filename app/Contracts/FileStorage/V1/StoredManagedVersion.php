<?php

namespace App\Contracts\FileStorage\V1;

class StoredManagedVersion
{
    public function __construct(
        public readonly int $fileId,
        public readonly int $versionId,
        public readonly int $possessionId,
        public readonly int $contentId,
        public readonly int $storageObjectId,
        public readonly ?int $replacedPossessionId,
    ) {}
}
