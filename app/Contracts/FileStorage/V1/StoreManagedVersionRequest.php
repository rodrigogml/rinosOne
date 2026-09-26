<?php

namespace App\Contracts\FileStorage\V1;

class StoreManagedVersionRequest
{
    public function __construct(
        public readonly FileStorageOwnerType $ownerType,
        public readonly int $ownerId,
        public readonly string $sourcePath,
        public readonly string $purpose,
        public readonly string $displayName,
        public readonly ?string $bindingKey = null,
        public readonly ?int $parentVersionId = null,
        public readonly ?string $backendKey = null,
    ) {}
}
