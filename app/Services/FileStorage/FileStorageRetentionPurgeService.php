<?php

namespace App\Services\FileStorage;

use App\Domain\FileStorage\Retention\FileStorageRetentionPolicy;
use App\Infrastructure\FileStorage\FileStorageBackendResolver;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileStorageObject;
use App\Models\FileStorage\StoredFileVersion;
use Illuminate\Support\Facades\DB;
use Throwable;

class FileStorageRetentionPurgeService
{
    public function __construct(
        private readonly FileStorageRetentionPolicy $retentionPolicy,
        private readonly FileStorageBackendResolver $backendResolver,
        private readonly FileStorageTechnicalLogger $technicalLogger,
    ) {}

    /**
     * Removes leaf versions that are no longer current for a possession after the configured recovery window.
     */
    public function purgeEligibleVersions(int $batchSize = 100): int
    {
        $this->purgeReleasedPossessionRecords($batchSize);

        $ids = StoredFileVersion::query()
            ->where('createdAt', '<=', $this->retentionPolicy->versionEligibleSince())
            ->whereDoesntHave('currentPossessions')
            ->whereDoesntHave('childVersions')
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');
        $purged = 0;

        foreach ($ids as $id) {
            $deleted = DB::transaction(function () use ($id): bool {
                $version = StoredFileVersion::query()->lockForUpdate()->find($id);

                if ($version === null
                    || $version->createdAt->greaterThan($this->retentionPolicy->versionEligibleSince())
                    || $version->currentPossessions()->exists()
                    || $version->childVersions()->exists()) {
                    return false;
                }

                $version->delete();

                return true;
            });
            $purged += $deleted ? 1 : 0;
        }

        return $purged;
    }

    /**
     * Removes released possession records only after the same recovery window that protects their bytes.
     */
    private function purgeReleasedPossessionRecords(int $batchSize): void
    {
        $ids = StoredFilePossession::query()
            ->where('state', 'RELEASED')
            ->whereNotNull('releasedAt')
            ->where('releasedAt', '<=', $this->retentionPolicy->versionEligibleSince())
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $possession = StoredFilePossession::query()->lockForUpdate()->find($id);

                if ($possession !== null
                    && $possession->state === 'RELEASED'
                    && $possession->releasedAt !== null
                    && $possession->releasedAt->lessThanOrEqualTo($this->retentionPolicy->versionEligibleSince())) {
                    $possession->delete();
                }
            });
        }
    }

    /**
     * Deletes a physical object only after its retention deadline and all catalog references are gone.
     */
    public function purgeEligibleStorageObjects(int $batchSize = 100): int
    {
        $ids = StoredFileStorageObject::query()
            ->whereNotNull('retentionUntil')
            ->where('retentionUntil', '<=', now())
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');
        $purged = 0;

        foreach ($ids as $id) {
            if ($this->purgeStorageObject($id)) {
                $purged++;
            }
        }

        return $purged;
    }

    private function purgeStorageObject(int $id): bool
    {
        $object = StoredFileStorageObject::query()->with('backend')->find($id);

        if ($object === null || $object->retentionUntil === null || $object->retentionUntil->isFuture()) {
            return false;
        }

        $contentHasReferences = StoredFileVersion::query()->where('idFileContent', $object->idFileContent)->exists()
            || $object->content->derivatives()->exists();

        if ($contentHasReferences || $object->backend === null) {
            return false;
        }

        try {
            $this->backendResolver->disk($object->backend->backendKey)->delete($object->storageKey);
        } catch (Throwable $exception) {
            $this->technicalLogger->failure('purge.storage-object.failed', $exception, [
                'storageObjectId' => $object->id,
                'backendKey' => $object->backend->backendKey,
            ]);

            return false;
        }

        return DB::transaction(function () use ($id): bool {
            $lockedObject = StoredFileStorageObject::query()->with('content')->lockForUpdate()->find($id);

            if ($lockedObject === null
                || $lockedObject->retentionUntil === null
                || $lockedObject->retentionUntil->isFuture()
                || StoredFileVersion::query()->where('idFileContent', $lockedObject->idFileContent)->exists()
                || $lockedObject->content->derivatives()->exists()) {
                return false;
            }

            $contentId = $lockedObject->idFileContent;
            $lockedObject->delete();

            $content = StoredFileContent::query()->lockForUpdate()->find($contentId);

            if ($content !== null && ! $content->versions()->exists() && ! $content->storageObjects()->exists() && ! $content->derivatives()->exists()) {
                $content->delete();
            }

            return true;
        });
    }
}
