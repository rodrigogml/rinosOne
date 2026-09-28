<?php

namespace App\Contracts\FileStorage\V1;

use App\Domain\FileStorage\Content\IngestedStoredFileContent;

class StoreWorkspaceVersionRequest
{
    public function __construct(
        public readonly FileStorageOwnerType $ownerType,
        public readonly int $ownerId,
        public readonly IngestedStoredFileContent $content,
        public readonly string $displayName,
        public readonly ?int $workspaceFolderId = null,
    ) {}
}
