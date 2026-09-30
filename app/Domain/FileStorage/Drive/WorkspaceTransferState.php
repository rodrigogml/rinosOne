<?php

namespace App\Domain\FileStorage\Drive;

enum WorkspaceTransferState: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            self::Pending, self::Processing => false,
        };
    }
}
