<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Exception\DriveWorkspaceProjectionException;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Support\Facades\DB;

/** Projects active direct resource relations without exposing source hierarchy. */
class SharedWithMeProjectionService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /** @return array{folders: list<array<string, mixed>>, files: list<array<string, mixed>>} */
    public function project(User $principal): array
    {
        $folders = [];
        $files = [];

        foreach ($this->directRelationsFor($principal) as $relation) {
            $scope = AuthorizationScope::from($relation->scope);
            $target = ['kind' => $scope === AuthorizationScope::Personal ? 'personal' : 'tenant', 'tenantId' => $relation->idTenant === null ? null : (int) $relation->idTenant];
            if ($relation->typeKey === 'personal.folder' || $relation->typeKey === 'tenant.folder') {
                $folder = WorkspaceFolder::query()->whereKey($relation->resourceId)->where('state', 'ACTIVE')->first();
                if ($folder === null) {
                    continue;
                }
                $resource = new ResourceReference($relation->typeKey, $folder->id, $scope, $relation->idTenant === null ? null : (int) $relation->idTenant);
                $read = $this->authorization->check($principal, $relation->typeKey.'.read', $scope, $resource->tenantId, $resource)->allowed;
                $edit = $this->authorization->check($principal, $relation->typeKey.'.edit', $scope, $resource->tenantId, $resource)->allowed;
                if (! $read && ! $edit) {
                    continue;
                }
                $folders[$relation->typeKey.':'.$folder->id] = [
                    'id' => $folder->id,
                    'kind' => 'folder',
                    'displayName' => $folder->displayName,
                    'originTarget' => $target,
                    'capabilities' => ['read' => $read || $edit, 'edit' => $edit, 'trash' => $edit],
                ];

                continue;
            }

            $possession = StoredFilePossession::query()->with('currentVersion.content')->whereKey($relation->resourceId)->where('storageArea', 'WORKSPACE')->where('state', 'ACTIVE')->first();
            if ($possession === null) {
                continue;
            }
            $resource = new ResourceReference($relation->typeKey, $possession->id, $scope, $relation->idTenant === null ? null : (int) $relation->idTenant);
            if (! $this->authorization->check($principal, $relation->typeKey.'.read', $scope, $resource->tenantId, $resource)->allowed) {
                continue;
            }
            $files[$relation->typeKey.':'.$possession->id] = [
                'id' => $possession->id,
                'kind' => 'file',
                'displayName' => $possession->displayName,
                'logicalSizeBytes' => $possession->logicalSizeBytes,
                'detectedMimeType' => $possession->currentVersion?->content?->detectedMimeType,
                'originTarget' => $target,
                'capabilities' => ['read' => true, 'edit' => false, 'trash' => false],
            ];
        }

        return ['folders' => array_values($folders), 'files' => array_values($files)];
    }

    /** @return array{item: array<string, mixed>, metadata: array<string, mixed>} */
    public function fileDetails(User $principal, int $possessionId): array
    {
        foreach ($this->project($principal)['files'] as $file) {
            if ($file['id'] === $possessionId) {
                return ['item' => $file, 'metadata' => []];
            }
        }

        throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
    }

    /** @return iterable<object{resourceId: int, typeKey: string, scope: string, idTenant: ?int}> */
    private function directRelationsFor(User $principal): iterable
    {
        return DB::table('auth_resource_relation')
            ->join('auth_resource_type', 'auth_resource_type.id', '=', 'auth_resource_relation.idResourceType')
            ->select([
                'auth_resource_relation.resourceId',
                'auth_resource_relation.scope',
                'auth_resource_relation.idTenant',
                'auth_resource_type.key as typeKey',
            ])
            ->where('auth_resource_relation.idUser', $principal->id)
            ->where('auth_resource_relation.active', true)
            ->where('auth_resource_type.active', true)
            ->whereIn('auth_resource_type.key', ['personal.folder', 'tenant.folder', 'personal.file', 'tenant.file'])
            ->whereIn('auth_resource_relation.relationKey', ['READ', 'EDIT'])
            ->orderBy('auth_resource_type.key')
            ->orderBy('auth_resource_relation.resourceId')
            ->get();
    }
}
