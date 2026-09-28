<?php

namespace App\Domain\FileStorage\Drive;

use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\Authorization\AuthorizationScope;
use InvalidArgumentException;

readonly class DriveWorkspaceTarget
{
    private function __construct(
        public FileStorageOwnerType $ownerType,
        public int $ownerId,
        public AuthorizationScope $scope,
        public ?int $tenantId,
        public string $resourceType,
    ) {
        $isPersonal = $ownerType === FileStorageOwnerType::User;
        if ($ownerId < 1
            || ($isPersonal && ($scope !== AuthorizationScope::Personal || $tenantId !== null || $resourceType !== 'personal.folder'))
            || (! $isPersonal && ($scope !== AuthorizationScope::Tenant || $tenantId !== $ownerId || $resourceType !== 'tenant.folder'))) {
            throw new InvalidArgumentException('The drive workspace target is invalid.');
        }
    }

    public static function personal(int $userId): self
    {
        return new self(FileStorageOwnerType::User, $userId, AuthorizationScope::Personal, null, 'personal.folder');
    }

    public static function work(int $tenantId): self
    {
        return new self(FileStorageOwnerType::Tenant, $tenantId, AuthorizationScope::Tenant, $tenantId, 'tenant.folder');
    }
}
