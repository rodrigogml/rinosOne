<?php

namespace App\Services\FileStorage;

use App\Contracts\FileStorage\V1\AuthorizedPrivateFileRead;
use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Exception\FileStorageAccessException;
use App\Infrastructure\FileStorage\FileStorageBackendResolver;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileStorageObject;
use App\Models\FileStorage\StoredFileSystemBinding;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Throwable;

class FileStoragePrivateReadService
{
    public function __construct(
        private readonly FileStorageBackendResolver $backendResolver,
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * Resolves a private byte stream for an owner without returning a backend, path, or public URL.
     */
    public function authorize(FilePrivateReadRequest $request): AuthorizedPrivateFileRead
    {
        $possession = $this->resolvePossession($request);
        $version = $possession->currentVersion;
        $content = $version?->content;
        $object = $content === null
            ? null
            : StoredFileStorageObject::query()
                ->with('backend')
                ->where('idFileContent', $content->id)
                ->where('state', 'ACTIVE')
                ->orderByRaw("CASE WHEN encoding = 'IDENTITY' THEN 0 ELSE 1 END")
                ->first();

        if ($content === null || $object?->backend === null) {
            throw new FileStorageAccessException('The requested private file content is unavailable.');
        }

        $backendKey = $object->backend->backendKey;
        $storageKey = $object->storageKey;
        $encoding = $object->encoding;

        return new AuthorizedPrivateFileRead(
            contentId: $content->id,
            detectedMimeType: $content->detectedMimeType,
            declaredExtension: $content->declaredExtension,
            logicalSizeBytes: $content->logicalSizeBytes,
            streamFactory: function () use ($backendKey, $storageKey, $encoding) {
                try {
                    $stream = $this->backendResolver->disk($backendKey)->readStream($storageKey);
                } catch (Throwable $exception) {
                    throw new FileStorageAccessException('The requested private file content is unavailable.', previous: $exception);
                }

                if (! is_resource($stream)) {
                    throw new FileStorageAccessException('The requested private file content is unavailable.');
                }

                if ($encoding === 'GZIP' && stream_filter_append($stream, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 31]) === false) {
                    fclose($stream);

                    throw new FileStorageAccessException('The requested private file content is unavailable.');
                }

                return $stream;
            },
        );
    }

    private function resolvePossession(FilePrivateReadRequest $request): StoredFilePossession
    {
        if ($request->ownerId < 1 || (($request->possessionId === null) === ($request->bindingKey === null))) {
            throw new FileStorageAccessException('The private file read request is invalid.');
        }

        $possession = $request->possessionId === null
            ? $this->resolveBindingPossession($request)
            : $this->resolveDirectPossession($request);

        if ($possession === null || ! in_array($possession->state, ['ACTIVE', 'TRASHED'], true)) {
            throw new FileStorageAccessException('The requested private file content is unavailable.');
        }
        $this->assertPrincipalCanRead($request, $possession);

        return $possession;
    }

    private function assertPrincipalCanRead(FilePrivateReadRequest $request, StoredFilePossession $possession): void
    {
        if ($request->principalUserId === null) {
            return;
        }
        $principal = User::query()->find($request->principalUserId);
        if ($principal === null) {
            throw new FileStorageAccessException('The requested private file content is unavailable.');
        }
        if ($possession->idUser === $principal->id) {
            return;
        }
        $folder = $possession->idWorkspaceFolder === null ? null : WorkspaceFolder::query()->find($possession->idWorkspaceFolder);
        if ($folder === null) {
            throw new FileStorageAccessException('The requested private file content is unavailable.');
        }
        $scope = $folder->idUser === null ? AuthorizationScope::Tenant : AuthorizationScope::Personal;
        $resource = new ResourceReference(
            $scope === AuthorizationScope::Tenant ? 'tenant.folder' : 'personal.folder',
            $folder->id,
            $scope,
            $folder->idTenant,
        );
        $decision = $this->authorization->check($principal, $resource->type.'.read', $scope, $folder->idTenant, $resource);
        if (! $decision->allowed) {
            throw new FileStorageAccessException('The requested private file content is unavailable.');
        }
    }

    private function resolveDirectPossession(FilePrivateReadRequest $request): ?StoredFilePossession
    {
        $ownerColumn = $request->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';

        return StoredFilePossession::query()
            ->whereKey($request->possessionId)
            ->where($ownerColumn, $request->ownerId)
            ->first();
    }

    private function resolveBindingPossession(FilePrivateReadRequest $request): ?StoredFilePossession
    {
        if ($request->ownerType !== FileStorageOwnerType::User || trim((string) $request->bindingKey) === '') {
            return null;
        }

        $binding = StoredFileSystemBinding::query()
            ->where('idUser', $request->ownerId)
            ->where('bindingKey', $request->bindingKey)
            ->first();

        if ($binding?->idFilePossession === null) {
            return null;
        }

        return StoredFilePossession::query()
            ->whereKey($binding->idFilePossession)
            ->where('idUser', $request->ownerId)
            ->first();
    }
}
