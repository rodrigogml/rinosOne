<?php

namespace App\Infrastructure\FileStorage\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\AuthorizationResourceAdapter;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\FileStorage\WorkspaceFolder;

class WorkspaceFolderAuthorizationResourceAdapter implements AuthorizationResourceAdapter
{
    public function __construct(private readonly AuthorizationScope $scope) {}

    public function typeKey(): string
    {
        return $this->scope === AuthorizationScope::Personal ? 'personal.folder' : 'tenant.folder';
    }

    public function exists(ResourceReference $resource): bool
    {
        if ($resource->type !== $this->typeKey() || $resource->scope !== $this->scope) {
            return false;
        }

        $query = WorkspaceFolder::query()
            ->whereKey($resource->id)
            ->where('state', 'ACTIVE');

        if ($this->scope === AuthorizationScope::Personal) {
            return $query->whereNotNull('idUser')->whereNull('idTenant')->exists();
        }

        return $query
            ->where('idTenant', $resource->tenantId)
            ->whereNull('idUser')
            ->exists();
    }

    public function supportedActions(): array
    {
        return [$this->typeKey().'.read', $this->typeKey().'.edit'];
    }

    public function supportedRelations(): array
    {
        return ['READ', 'EDIT'];
    }

    public function inheritedResourceIds(ResourceReference $resource): array
    {
        if (! $this->exists($resource)) {
            return [];
        }

        $ids = [];
        $folder = WorkspaceFolder::query()->find($resource->id);
        while ($folder !== null) {
            $ids[] = $folder->id;
            $folder = $folder->idParentFolder === null ? null : WorkspaceFolder::query()->find($folder->idParentFolder);
        }

        return $ids;
    }

    public function isWorkspacePrincipal(ResourceReference $resource, int $userId): bool
    {
        return $this->scope === AuthorizationScope::Personal
            && WorkspaceFolder::query()->whereKey($resource->id)->where('idUser', $userId)->where('state', 'ACTIVE')->exists();
    }
}
