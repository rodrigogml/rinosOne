<?php

namespace App\Services\FileStorage;

class FileStorageReconciliationResult
{
    public function __construct(
        public int $missingCatalogObjects = 0,
        public int $staleWritingObjects = 0,
        public int $deletedPhysicalOrphans = 0,
        public int $unavailableBackends = 0,
    ) {}
}
