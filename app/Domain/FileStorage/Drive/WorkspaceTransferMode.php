<?php

namespace App\Domain\FileStorage\Drive;

enum WorkspaceTransferMode: string
{
    case Copy = 'COPY';
    case Move = 'MOVE';
}
