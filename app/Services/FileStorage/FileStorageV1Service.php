<?php

namespace App\Services\FileStorage;

use App\Contracts\FileStorage\V1\AuthorizedPrivateFileRead;
use App\Contracts\FileStorage\V1\FilePossessionLifecycleResult;
use App\Contracts\FileStorage\V1\FilePossessionOperationRequest;
use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\ManagedBindingStatus;
use App\Contracts\FileStorage\V1\ManagedBindingStatusRequest;
use App\Contracts\FileStorage\V1\ReleaseManagedBindingRequest;
use App\Contracts\FileStorage\V1\ReserveFileVersionDerivativeRequest;
use App\Contracts\FileStorage\V1\StoredManagedVersion;
use App\Contracts\FileStorage\V1\StoreFileVersionMetadataRequest;
use App\Contracts\FileStorage\V1\StoreManagedVersionRequest;
use App\Domain\FileStorage\Exception\FileStorageVersionException;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileSystemBinding;
use App\Models\FileStorage\StoredFileVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class FileStorageV1Service implements FileStorageV1
{
    public function __construct(
        private readonly FileStorageContentIngestionService $contentIngestionService,
        private readonly FileStoragePossessionLifecycleService $possessionLifecycleService,
        private readonly FileStoragePrivateReadService $privateReadService,
        private readonly FileStorageMetadataService $metadataService,
        private readonly FileStorageDerivativeService $derivativeService,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws FileStorageVersionException when the owner, binding, parent version, or persistent association is invalid.
     */
    public function storeManagedVersion(StoreManagedVersionRequest $request): StoredManagedVersion
    {
        $this->validateRequest($request);
        $ingestedContent = $this->contentIngestionService->ingest($request->sourcePath, $request->backendKey);

        try {
            return DB::transaction(function () use ($request, $ingestedContent): StoredManagedVersion {
                $binding = $request->bindingKey === null ? null : $this->resolveBinding($request);
                $replacedPossession = $binding?->idFilePossession === null
                    ? null
                    : StoredFilePossession::query()->lockForUpdate()->find($binding->idFilePossession);
                $parentVersion = $this->resolveParentVersion($request, $replacedPossession);
                $file = $this->resolveFile($parentVersion, $replacedPossession);
                $version = StoredFileVersion::query()->create([
                    'idFile' => $file->id,
                    'idParentFileVersion' => $parentVersion?->id,
                    'idFileContent' => $ingestedContent->contentId,
                    'versionNumber' => $this->nextVersionNumber($file->id),
                ]);
                $possession = StoredFilePossession::query()->create([
                    'idFile' => $file->id,
                    'idCurrentFileVersion' => $version->id,
                    'idUser' => $request->ownerType === FileStorageOwnerType::User ? $request->ownerId : null,
                    'idTenant' => $request->ownerType === FileStorageOwnerType::Tenant ? $request->ownerId : null,
                    'storageArea' => 'SYSTEM_MANAGED',
                    'purpose' => $request->purpose,
                    'displayName' => $request->displayName,
                    'state' => 'ACTIVE',
                    'logicalSizeBytes' => $ingestedContent->logicalSizeBytes,
                ]);

                $this->replaceBinding($binding, $possession);
                $this->releaseReplacedPossession($replacedPossession);
                $this->adjustOwnerUsage(
                    $request->ownerType,
                    $request->ownerId,
                    $ingestedContent->logicalSizeBytes,
                    $replacedPossession?->logicalSizeBytes ?? 0,
                );

                return new StoredManagedVersion(
                    fileId: $file->id,
                    versionId: $version->id,
                    possessionId: $possession->id,
                    contentId: $ingestedContent->contentId,
                    storageObjectId: $ingestedContent->storageObjectId,
                    replacedPossessionId: $replacedPossession?->id,
                );
            });
        } catch (FileStorageVersionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FileStorageVersionException('The managed file version could not be stored.', previous: $exception);
        }
    }

    /** {@inheritDoc} */
    public function releaseManagedBinding(ReleaseManagedBindingRequest $request): void
    {
        if ($request->ownerId < 1 || trim($request->bindingKey) === '' || trim($request->purpose) === '') {
            throw new FileStorageVersionException('The managed file binding release request is invalid.');
        }

        DB::transaction(function () use ($request): void {
            $binding = StoredFileSystemBinding::query()
                ->where('idUser', $request->ownerId)
                ->where('bindingKey', $request->bindingKey)
                ->lockForUpdate()
                ->first();

            if ($binding?->idFilePossession === null) {
                return;
            }

            $possession = StoredFilePossession::query()
                ->whereKey($binding->idFilePossession)
                ->where('idUser', $request->ownerId)
                ->lockForUpdate()
                ->first();

            if ($possession === null
                || $possession->storageArea !== 'SYSTEM_MANAGED'
                || $possession->purpose !== $request->purpose
                || $possession->state !== 'ACTIVE') {
                throw new FileStorageVersionException('The managed file binding does not reference an active matching possession.');
            }

            $binding->forceFill(['idFilePossession' => null])->save();
            $this->possessionLifecycleService->releaseSupersededSystemPossession($possession);
            $this->adjustOwnerUsage(
                FileStorageOwnerType::User,
                $request->ownerId,
                0,
                $possession->logicalSizeBytes,
            );
        });
    }

    /** {@inheritDoc} */
    public function managedBindingStatus(ManagedBindingStatusRequest $request): ManagedBindingStatus
    {
        if ($request->ownerId < 1 || trim($request->bindingKey) === '' || trim($request->purpose) === '') {
            throw new FileStorageVersionException('The managed file binding status request is invalid.');
        }

        $binding = StoredFileSystemBinding::query()
            ->where('idUser', $request->ownerId)
            ->where('bindingKey', $request->bindingKey)
            ->first();

        if ($binding?->idFilePossession === null) {
            return new ManagedBindingStatus(false, null);
        }

        $possession = StoredFilePossession::query()
            ->whereKey($binding->idFilePossession)
            ->where('idUser', $request->ownerId)
            ->where('storageArea', 'SYSTEM_MANAGED')
            ->where('purpose', $request->purpose)
            ->where('state', 'ACTIVE')
            ->first();

        return new ManagedBindingStatus(
            available: $possession !== null,
            updatedAt: $possession?->createdAt?->toImmutable(),
        );
    }

    /** {@inheritDoc} */
    public function trashPossession(FilePossessionOperationRequest $request): FilePossessionLifecycleResult
    {
        return $this->possessionLifecycleService->trash($request);
    }

    /** {@inheritDoc} */
    public function restorePossession(FilePossessionOperationRequest $request): FilePossessionLifecycleResult
    {
        return $this->possessionLifecycleService->restore($request);
    }

    /** {@inheritDoc} */
    public function releasePossession(FilePossessionOperationRequest $request): FilePossessionLifecycleResult
    {
        return $this->possessionLifecycleService->release($request);
    }

    /** {@inheritDoc} */
    public function authorizePrivateRead(FilePrivateReadRequest $request): AuthorizedPrivateFileRead
    {
        return $this->privateReadService->authorize($request);
    }

    /** {@inheritDoc} */
    public function storeVersionMetadata(StoreFileVersionMetadataRequest $request): void
    {
        $this->metadataService->store($request);
    }

    /** {@inheritDoc} */
    public function reserveVersionDerivative(ReserveFileVersionDerivativeRequest $request): void
    {
        $this->derivativeService->reserve($request);
    }

    private function validateRequest(StoreManagedVersionRequest $request): void
    {
        if ($request->ownerId < 1 || trim($request->purpose) === '' || trim($request->displayName) === '') {
            throw new FileStorageVersionException('The managed file version request is invalid.');
        }

        if ($request->bindingKey !== null
            && ($request->ownerType !== FileStorageOwnerType::User || trim($request->bindingKey) === '')) {
            throw new FileStorageVersionException('A managed file binding requires a user owner and a valid binding key.');
        }
    }

    private function resolveBinding(StoreManagedVersionRequest $request): StoredFileSystemBinding
    {
        $binding = StoredFileSystemBinding::query()
            ->where('idUser', $request->ownerId)
            ->where('bindingKey', $request->bindingKey)
            ->lockForUpdate()
            ->first();

        if ($binding !== null) {
            return $binding;
        }

        try {
            return StoredFileSystemBinding::query()->create([
                'idUser' => $request->ownerId,
                'bindingKey' => $request->bindingKey,
            ]);
        } catch (QueryException) {
            return StoredFileSystemBinding::query()
                ->where('idUser', $request->ownerId)
                ->where('bindingKey', $request->bindingKey)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function resolveParentVersion(StoreManagedVersionRequest $request, ?StoredFilePossession $replacedPossession): ?StoredFileVersion
    {
        if ($request->parentVersionId !== null) {
            $parentVersion = StoredFileVersion::query()->lockForUpdate()->find($request->parentVersionId);

            if ($parentVersion === null) {
                throw new FileStorageVersionException('The managed file version parent does not exist.');
            }

            return $parentVersion;
        }

        if ($replacedPossession === null) {
            return null;
        }

        return StoredFileVersion::query()->lockForUpdate()->find($replacedPossession->idCurrentFileVersion);
    }

    private function resolveFile(?StoredFileVersion $parentVersion, ?StoredFilePossession $replacedPossession): StoredFile
    {
        if ($parentVersion !== null
            && $replacedPossession !== null
            && $parentVersion->idFile !== $replacedPossession->idFile) {
            throw new FileStorageVersionException('The managed file version parent does not belong to the active binding lineage.');
        }

        $fileId = $parentVersion?->idFile ?? $replacedPossession?->idFile;

        if ($fileId === null) {
            return StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        }

        $file = StoredFile::query()->lockForUpdate()->find($fileId);

        if ($file === null) {
            throw new FileStorageVersionException('The managed file lineage does not exist.');
        }

        return $file;
    }

    private function nextVersionNumber(int $fileId): int
    {
        $latestVersion = StoredFileVersion::query()
            ->where('idFile', $fileId)
            ->lockForUpdate()
            ->orderByDesc('versionNumber')
            ->first();

        return ($latestVersion?->versionNumber ?? 0) + 1;
    }

    private function replaceBinding(?StoredFileSystemBinding $binding, StoredFilePossession $possession): void
    {
        if ($binding === null) {
            return;
        }

        $binding->forceFill(['idFilePossession' => $possession->id])->save();
    }

    private function releaseReplacedPossession(?StoredFilePossession $replacedPossession): void
    {
        if ($replacedPossession === null) {
            return;
        }

        $this->possessionLifecycleService->releaseSupersededSystemPossession($replacedPossession);
    }

    private function adjustOwnerUsage(FileStorageOwnerType $ownerType, int $ownerId, int $newSizeBytes, int $replacedSizeBytes): void
    {
        $usage = $this->resolveOwnerUsage($ownerType, $ownerId);
        $delta = $newSizeBytes - $replacedSizeBytes;

        $usage->forceFill([
            'systemManagedBytes' => max(0, (int) $usage->systemManagedBytes + $delta),
            'totalBytes' => max(0, (int) $usage->totalBytes + $delta),
        ])->save();
    }

    private function resolveOwnerUsage(FileStorageOwnerType $ownerType, int $ownerId): StoredFileOwnerUsage
    {
        $ownerColumn = $ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';
        $usage = StoredFileOwnerUsage::query()->where($ownerColumn, $ownerId)->lockForUpdate()->first();

        if ($usage !== null) {
            return $usage;
        }

        try {
            return StoredFileOwnerUsage::query()->create([
                $ownerColumn => $ownerId,
                'workspaceBytes' => 0,
                'systemManagedBytes' => 0,
                'trashBytes' => 0,
                'totalBytes' => 0,
            ]);
        } catch (QueryException) {
            return StoredFileOwnerUsage::query()->where($ownerColumn, $ownerId)->lockForUpdate()->firstOrFail();
        }
    }
}
