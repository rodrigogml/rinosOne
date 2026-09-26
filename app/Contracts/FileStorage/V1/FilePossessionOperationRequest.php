<?php

namespace App\Contracts\FileStorage\V1;

class FilePossessionOperationRequest
{
    public function __construct(
        public readonly FileStorageOwnerType $ownerType,
        public readonly int $ownerId,
        public readonly int $possessionId,
    ) {}
}
