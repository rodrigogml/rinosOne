<?php

namespace App\Infrastructure\FileStorage\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\AuthorizationResourceAdapter;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\FileStorage\StoredFilePossession;

/**
 * Exposes an active workspace file possession as a direct read-only resource.
 * File relations never inherit from a folder and are intentionally limited to users.
 */
class WorkspaceFileAuthorizationResourceAdapter implements AuthorizationResourceAdapter
{
    public function __construct(private readonly AuthorizationScope $scope) {}

    public function typeKey(): string
    {
        return $this->scope === AuthorizationScope::Personal ? 'personal.file' : 'tenant.file';
    }

    public function exists(ResourceReference $resource): bool
    {
        if ($resource->type !== $this->typeKey() || $resource->scope !== $this->scope) {
            return false;
        }

        $query = StoredFilePossession::query()
            ->whereKey($resource->id)
            ->where('storageArea', 'WORKSPACE')
            ->where('state', 'ACTIVE');

        if ($this->scope === AuthorizationScope::Personal) {
            return $query->whereNotNull('idUser')->whereNull('idTenant')->exists();
        }

        return $query->where('idTenant', $resource->tenantId)->whereNull('idUser')->exists();
    }

    public function supportedActions(): array
    {
        return [$this->typeKey().'.read'];
    }

    public function supportedRelations(): array
    {
        return ['READ'];
    }

    public function allowsGroupRelations(): bool
    {
        return false;
    }

    public function inheritedResourceIds(ResourceReference $resource): array
    {
        return $this->exists($resource) ? [$resource->id] : [];
    }

    public function isWorkspacePrincipal(ResourceReference $resource, int $userId): bool
    {
        return $this->scope === AuthorizationScope::Personal
            && StoredFilePossession::query()
                ->whereKey($resource->id)
                ->where('idUser', $userId)
                ->whereNull('idTenant')
                ->where('storageArea', 'WORKSPACE')
                ->where('state', 'ACTIVE')
                ->exists();
    }
}
