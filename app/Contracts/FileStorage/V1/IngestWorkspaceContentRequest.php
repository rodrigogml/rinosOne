<?php

namespace App\Contracts\FileStorage\V1;

class IngestWorkspaceContentRequest
{
    public function __construct(public readonly string $sourcePath, public readonly ?string $backendKey = null) {}
}
