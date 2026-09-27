<?php

namespace App\Contracts\FileStorage\V1;

class StoreFileVersionMetadataRequest
{
    /**
     * @param  array<string, mixed>  $metadataValue
     */
    public function __construct(
        public readonly FileStorageOwnerType $ownerType,
        public readonly int $ownerId,
        public readonly int $possessionId,
        public readonly string $metadataKey,
        public readonly array $metadataValue,
        public readonly string $source,
        public readonly ?int $principalUserId = null,
    ) {}
}
