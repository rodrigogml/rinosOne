<?php

namespace App\Services\FileStorage;

use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\StoreFileVersionMetadataRequest;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Exception\FileStorageMetadataException;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersionMetadata;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Support\Facades\DB;

class FileStorageMetadataService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /**
     * Persists source-attributed metadata for the current version of an owner-authorized possession.
     */
    public function store(StoreFileVersionMetadataRequest $request): void
    {
        if ($request->ownerId < 1
            || $request->possessionId < 1
            || trim($request->metadataKey) === ''
            || mb_strlen($request->metadataKey) > 120
            || $request->metadataValue === []
            || ! in_array($request->source, ['EXTRACTED', 'DECLARED'], true)) {
            throw new FileStorageMetadataException('The file version metadata request is invalid.');
        }

        DB::transaction(function () use ($request): void {
            $ownerColumn = $request->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';
            $possession = StoredFilePossession::query()
                ->whereKey($request->possessionId)
                ->where($ownerColumn, $request->ownerId)
                ->whereIn('state', ['ACTIVE', 'TRASHED'])
                ->lockForUpdate()
                ->first();

            if ($possession === null) {
                throw new FileStorageMetadataException('The file version is not available to this owner.');
            }
            $this->assertPrincipalCanEdit($request, $possession);

            StoredFileVersionMetadata::query()->create([
                'idFileVersion' => $possession->idCurrentFileVersion,
                'metadataKey' => $request->metadataKey,
                'metadataValue' => $request->metadataValue,
                'source' => $request->source,
            ]);
        });
    }

    /**
     * Requires an explicit EDIT relation when a collaborator mutates a workspace file.
     * Internal owner-scoped calls retain their established contract by omitting the principal.
     */
    private function assertPrincipalCanEdit(StoreFileVersionMetadataRequest $request, StoredFilePossession $possession): void
    {
        if ($request->principalUserId === null) {
            return;
        }
        $principal = User::query()->find($request->principalUserId);
        if ($principal === null || $possession->idUser === $principal->id) {
            if ($principal !== null) {
                return;
            }

            throw new FileStorageMetadataException('The file version is not available to this owner.');
        }
        $folder = $possession->idWorkspaceFolder === null ? null : WorkspaceFolder::query()->find($possession->idWorkspaceFolder);
        if ($folder === null) {
            throw new FileStorageMetadataException('The file version is not available to this owner.');
        }
        $scope = $folder->idUser === null ? AuthorizationScope::Tenant : AuthorizationScope::Personal;
        $resource = new ResourceReference($scope === AuthorizationScope::Tenant ? 'tenant.folder' : 'personal.folder', $folder->id, $scope, $folder->idTenant);
        if (! $this->authorization->check($principal, $resource->type.'.edit', $scope, $folder->idTenant, $resource)->allowed) {
            throw new FileStorageMetadataException('The file version is not available to this owner.');
        }
    }
}
