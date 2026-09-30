<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceTargetException;
use App\Models\User;

class DriveWorkspaceTargetResolver
{
    public function __construct(private readonly DriveCatalogService $catalog) {}

    /** Resolves the authenticated user's only personal Drive workspace. */
    public function personal(User $principal): DriveWorkspaceTarget
    {
        return DriveWorkspaceTarget::personal($principal->id);
    }

    /** Resolves a Work workspace only when an effective Drive candidate exists for the principal. */
    public function work(User $principal, int $tenantId): DriveWorkspaceTarget
    {
        if ($tenantId < 1 || ! $this->catalog->canResolveWork($principal, $tenantId)) {
            throw new DriveWorkspaceTargetException('The requested organization workspace is unavailable.');
        }

        return DriveWorkspaceTarget::work($tenantId);
    }
}
