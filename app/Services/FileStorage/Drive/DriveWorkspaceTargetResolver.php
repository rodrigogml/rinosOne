<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceTargetException;
use App\Models\User;
use App\Services\Tenant\TenantContextResolver;

class DriveWorkspaceTargetResolver
{
    public function __construct(private readonly TenantContextResolver $tenantContexts) {}

    /** Resolves the authenticated user's only personal Drive workspace. */
    public function personal(User $principal): DriveWorkspaceTarget
    {
        return DriveWorkspaceTarget::personal($principal->id);
    }

    /** Resolves a Work workspace only when the authenticated user has an active tenant context. */
    public function work(User $principal, int $tenantId): DriveWorkspaceTarget
    {
        if ($tenantId < 1 || $this->tenantContexts->resolveMembership($principal, (string) $tenantId) === null) {
            throw new DriveWorkspaceTargetException('The requested organization workspace is unavailable.');
        }

        return DriveWorkspaceTarget::work($tenantId);
    }
}
