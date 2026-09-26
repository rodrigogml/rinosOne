<?php

namespace App\Services\FileStorage;

use App\Domain\FileStorage\Retention\FileStorageRetentionPolicy;
use App\Infrastructure\FileStorage\FileStorageBackendResolver;
use App\Models\FileStorage\StoredFileStorageBackend;
use App\Models\FileStorage\StoredFileStorageObject;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

class FileStorageReconciliationService
{
    public function __construct(
        private readonly FileStorageBackendResolver $backendResolver,
        private readonly FileStorageRetentionPolicy $retentionPolicy,
        private readonly FileStorageTechnicalLogger $technicalLogger,
    ) {}

    /**
     * Reconciles configured private backends without deriving ownership from filesystem paths.
     */
    public function reconcile(): FileStorageReconciliationResult
    {
        $result = new FileStorageReconciliationResult;

        foreach (array_keys(config('file-storage.backends', [])) as $backendKey) {
            try {
                $disk = $this->backendResolver->disk($backendKey);
                $backend = StoredFileStorageBackend::query()->where('backendKey', $backendKey)->first();
                $this->reconcileCatalogObjects($backend, $disk, $result);
                $this->reconcilePhysicalObjects($backend, $disk, $result);
            } catch (Throwable $exception) {
                $this->technicalLogger->failure('reconciliation.backend.failed', $exception, ['backendKey' => $backendKey]);
                $result->unavailableBackends++;
            }
        }

        return $result;
    }

    private function reconcileCatalogObjects(?StoredFileStorageBackend $backend, Filesystem $disk, FileStorageReconciliationResult $result): void
    {
        if ($backend === null) {
            return;
        }

        StoredFileStorageObject::query()
            ->where('idStorageBackend', $backend->id)
            ->whereIn('state', ['ACTIVE', 'WRITING'])
            ->orderBy('id')
            ->each(function (StoredFileStorageObject $object) use ($disk, $result): void {
                if ($object->state === 'ACTIVE' && ! $disk->exists($object->storageKey)) {
                    $object->forceFill(['state' => 'ORPHANED'])->save();
                    $result->missingCatalogObjects++;

                    return;
                }

                if ($object->state === 'WRITING' && $object->createdAt->lessThanOrEqualTo($this->orphanEligibleSince())) {
                    $object->forceFill([
                        'state' => 'ORPHANED',
                        'retentionUntil' => now(),
                    ])->save();
                    $result->staleWritingObjects++;
                }
            });
    }

    private function reconcilePhysicalObjects(?StoredFileStorageBackend $backend, Filesystem $disk, FileStorageReconciliationResult $result): void
    {
        $knownStorageKeys = $backend === null
            ? []
            : array_flip(StoredFileStorageObject::query()
                ->where('idStorageBackend', $backend->id)
                ->pluck('storageKey')
                ->all());

        foreach ($disk->allFiles('objects/sha256') as $storageKey) {
            if (isset($knownStorageKeys[$storageKey]) || $disk->lastModified($storageKey) > $this->orphanEligibleSince()->getTimestamp()) {
                continue;
            }

            if ($disk->delete($storageKey)) {
                $result->deletedPhysicalOrphans++;
            }
        }
    }

    private function orphanEligibleSince(): CarbonImmutable
    {
        return $this->retentionPolicy->orphanEligibleSince();
    }
}
