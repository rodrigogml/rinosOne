<?php

namespace App\Contracts\FileStorage\V1;

class FilePrivateReadRequest
{
    public function __construct(
        public readonly FileStorageOwnerType $ownerType,
        public readonly int $ownerId,
        public readonly ?int $possessionId = null,
        public readonly ?string $bindingKey = null,
        public readonly ?int $principalUserId = null,
        public readonly bool $allowTrashed = true,
    ) {}
}
