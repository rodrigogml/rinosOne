<?php

namespace App\Services\FileStorage;

use App\Domain\FileStorage\Compression\PreparedFileStorageRepresentation;
use App\Domain\FileStorage\Exception\FileStorageIngestionException;
use App\Domain\FileStorage\Retention\FileStorageRetentionPolicy;
use App\Infrastructure\FileStorage\FileStorageBackendResolver;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileStorageObject;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FileStorageCompressionReprocessor
{
    public function __construct(
        private readonly FileStorageBackendResolver $backendResolver,
        private readonly FileStorageCompressionService $compressionService,
        private readonly FileStorageRetentionPolicy $retentionPolicy,
        private readonly FileStorageTechnicalLogger $technicalLogger,
    ) {}

    /**
     * Reprocesses a bounded content batch and changes representation only when the current policy requires it.
     */
    public function reprocess(int $batchSize = 100): int
    {
        $contentIds = StoredFileContent::query()
            ->whereHas('storageObjects', fn ($query) => $query->where('state', 'ACTIVE'))
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');
        $reprocessed = 0;

        foreach ($contentIds as $contentId) {
            if ($this->reprocessContent($contentId)) {
                $reprocessed++;
            }
        }

        return $reprocessed;
    }

    private function reprocessContent(int $contentId): bool
    {
        $content = StoredFileContent::query()->find($contentId);
        $currentObject = $content?->storageObjects()->with('backend')->where('state', 'ACTIVE')->orderBy('id')->first();

        if ($content === null || $currentObject?->backend === null) {
            return false;
        }

        $backendKey = $currentObject->backend->backendKey;
        $disk = $this->backendResolver->disk($backendKey);
        $this->backendResolver->ensureWritable($backendKey);
        $logicalStagingKey = '.file-storage-staging/'.Str::uuid().'.logical';
        $representation = null;

        try {
            $this->materializeLogicalStaging($disk, $currentObject, $logicalStagingKey);
            $representation = $this->compressionService->prepare($disk, $logicalStagingKey, [
                'logicalSha256' => $content->logicalSha256,
                'logicalSizeBytes' => $content->logicalSizeBytes,
                'detectedMimeType' => $content->detectedMimeType,
                'declaredExtension' => $content->declaredExtension,
            ]);

            if ($representation->encoding === $currentObject->encoding
                && hash_equals($representation->storedSha256, $currentObject->storedSha256)) {
                return false;
            }

            return DB::transaction(function () use ($content, $currentObject, $representation, $disk): bool {
                $current = StoredFileStorageObject::query()->lockForUpdate()->find($currentObject->id);

                if ($current === null || $current->state !== 'ACTIVE') {
                    return false;
                }

                $target = StoredFileStorageObject::query()
                    ->where('idStorageBackend', $current->idStorageBackend)
                    ->where('storedSha256', $representation->storedSha256)
                    ->where('encoding', $representation->encoding)
                    ->lockForUpdate()
                    ->first();
                $storageKey = $this->storageKey($representation->storedSha256);

                if ($target === null) {
                    $target = StoredFileStorageObject::query()->create([
                        'idFileContent' => $content->id,
                        'idStorageBackend' => $current->idStorageBackend,
                        'storedSha256' => $representation->storedSha256,
                        'storageKey' => $storageKey,
                        'encoding' => $representation->encoding,
                        'storedSizeBytes' => $representation->storedSizeBytes,
                        'state' => 'WRITING',
                    ]);
                }

                if ($target->state !== 'ACTIVE') {
                    $this->promote($disk, $representation, $storageKey);
                }

                $target->forceFill([
                    'idFileContent' => $content->id,
                    'storageKey' => $storageKey,
                    'storedSizeBytes' => $representation->storedSizeBytes,
                    'state' => 'ACTIVE',
                    'retentionUntil' => null,
                ])->save();
                StoredFileStorageObject::query()
                    ->where('idFileContent', $content->id)
                    ->where('state', 'ACTIVE')
                    ->whereKeyNot($target->id)
                    ->update([
                        'state' => 'RETIRED',
                        'retentionUntil' => $this->retentionPolicy->retentionUntil(),
                    ]);

                return true;
            });
        } catch (Throwable $exception) {
            $this->technicalLogger->failure('compression.reprocess.failed', $exception, ['contentId' => $contentId]);

            if ($exception instanceof FileStorageIngestionException) {
                throw $exception;
            }

            throw new FileStorageIngestionException('The file storage compression reprocessing could not be completed.', previous: $exception);
        } finally {
            $this->deleteIfPresent($disk, $logicalStagingKey);

            if ($representation !== null && $representation->stagingKey !== $logicalStagingKey) {
                $this->deleteIfPresent($disk, $representation->stagingKey);
            }
        }
    }

    private function materializeLogicalStaging(Filesystem $disk, StoredFileStorageObject $object, string $logicalStagingKey): void
    {
        $stream = $disk->readStream($object->storageKey);

        if (! is_resource($stream)) {
            throw new FileStorageIngestionException('The stored file representation cannot be reprocessed.');
        }

        try {
            if ($object->encoding === 'GZIP' && stream_filter_append($stream, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 31]) === false) {
                throw new FileStorageIngestionException('The stored file representation cannot be reprocessed.');
            }

            if ($disk->writeStream($logicalStagingKey, $stream) !== true) {
                throw new FileStorageIngestionException('The stored file representation cannot be reprocessed.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function promote(Filesystem $disk, PreparedFileStorageRepresentation $representation, string $storageKey): void
    {
        if ($disk->exists($storageKey)) {
            $stream = $disk->readStream($storageKey);

            if (! is_resource($stream)) {
                throw new FileStorageIngestionException('The existing reprocessed file representation cannot be inspected.');
            }

            try {
                $hash = hash_init('sha256');
                $bytes = hash_update_stream($hash, $stream);
                $storedSha256 = hash_final($hash);
            } finally {
                fclose($stream);
            }

            if ($bytes === false || ! hash_equals($representation->storedSha256, $storedSha256)) {
                throw new FileStorageIngestionException('The existing reprocessed file representation does not match its content hash.');
            }

            return;
        }

        if ($disk->move($representation->stagingKey, $storageKey) !== true) {
            throw new FileStorageIngestionException('The reprocessed file representation cannot be promoted.');
        }
    }

    private function deleteIfPresent(Filesystem $disk, string $storageKey): void
    {
        try {
            if ($disk->exists($storageKey)) {
                $disk->delete($storageKey);
            }
        } catch (Throwable) {
        }
    }

    private function storageKey(string $storedSha256): string
    {
        return 'objects/sha256/'.substr($storedSha256, 0, 2).'/'.substr($storedSha256, 2, 2).'/'.$storedSha256.'.blob';
    }
}
