<?php

namespace App\Services\FileStorage;

use App\Domain\FileStorage\Content\IngestedStoredFileContent;
use App\Domain\FileStorage\Exception\FileStorageIngestionException;
use App\Infrastructure\FileStorage\FileStorageBackendResolver;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileStorageBackend;
use App\Models\FileStorage\StoredFileStorageObject;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FileStorageContentIngestionService
{
    public function __construct(
        private readonly FileStorageBackendResolver $backendResolver,
        private readonly FileStorageCompressionService $compressionService,
        private readonly FileStorageTechnicalLogger $technicalLogger,
    ) {}

    /**
     * Stores an immutable content representation and reuses an active object when its bytes already exist.
     *
     * @throws FileStorageIngestionException when the source cannot be staged, inspected, promoted, or recorded safely.
     */
    public function ingest(string $sourcePath, ?string $backendKey = null): IngestedStoredFileContent
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new FileStorageIngestionException('The file storage source is unavailable.');
        }

        $disk = $this->backendResolver->disk($backendKey);
        $this->backendResolver->ensureWritable($backendKey);
        $stagingKey = '.file-storage-staging/'.Str::uuid().'.staging';
        $representation = null;

        try {
            $this->stage($disk, $stagingKey, $sourcePath);
            $descriptor = $this->describe($disk, $stagingKey, $sourcePath);
            $representation = $this->compressionService->prepare($disk, $stagingKey, $descriptor);

            $result = DB::transaction(function () use ($backendKey, $descriptor, $disk, $representation): IngestedStoredFileContent {
                $content = $this->resolveContent($descriptor);
                $backend = $this->resolveBackend($backendKey);
                $storageKey = $this->storageKey($representation->storedSha256);
                $object = StoredFileStorageObject::query()
                    ->where('idStorageBackend', $backend->id)
                    ->where('storedSha256', $representation->storedSha256)
                    ->where('encoding', $representation->encoding)
                    ->lockForUpdate()
                    ->first();

                if ($object?->state === 'ACTIVE') {
                    $this->deleteIfPresent($disk, $representation->stagingKey);

                    return $this->result($content, $object, true);
                }

                if ($object === null) {
                    $object = StoredFileStorageObject::query()->create([
                        'idFileContent' => $content->id,
                        'idStorageBackend' => $backend->id,
                        'storedSha256' => $representation->storedSha256,
                        'storageKey' => $storageKey,
                        'encoding' => $representation->encoding,
                        'storedSizeBytes' => $representation->storedSizeBytes,
                        'state' => 'WRITING',
                    ]);
                }

                $this->promote($disk, $representation->stagingKey, $storageKey, $representation->storedSha256);

                $object->forceFill([
                    'idFileContent' => $content->id,
                    'storageKey' => $storageKey,
                    'storedSizeBytes' => $representation->storedSizeBytes,
                    'state' => 'ACTIVE',
                ])->save();

                return $this->result($content, $object, false);
            });

            $this->deleteIfPresent($disk, $stagingKey);

            if ($representation->stagingKey !== $stagingKey) {
                $this->deleteIfPresent($disk, $representation->stagingKey);
            }

            return $result;
        } catch (FileStorageIngestionException $exception) {
            $this->deleteIfPresent($disk, $stagingKey);

            if ($representation !== null && $representation->stagingKey !== $stagingKey) {
                $this->deleteIfPresent($disk, $representation->stagingKey);
            }

            $this->technicalLogger->failure('ingestion.failed', $exception, ['backendKey' => $backendKey ?? config('file-storage.defaultBackend')]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteIfPresent($disk, $stagingKey);

            if ($representation !== null && $representation->stagingKey !== $stagingKey) {
                $this->deleteIfPresent($disk, $representation->stagingKey);
            }

            $this->technicalLogger->failure('ingestion.failed', $exception, ['backendKey' => $backendKey ?? config('file-storage.defaultBackend')]);

            throw new FileStorageIngestionException('The file storage content could not be ingested.', previous: $exception);
        }
    }

    /**
     * @param  array{logicalSha256: string, logicalSizeBytes: int, detectedMimeType: string, declaredExtension: ?string}  $descriptor
     */
    private function resolveContent(array $descriptor): StoredFileContent
    {
        $content = StoredFileContent::query()
            ->where('logicalSha256', $descriptor['logicalSha256'])
            ->lockForUpdate()
            ->first();

        if ($content !== null) {
            return $content;
        }

        try {
            return StoredFileContent::query()->create($descriptor);
        } catch (QueryException) {
            return StoredFileContent::query()
                ->where('logicalSha256', $descriptor['logicalSha256'])
                ->firstOrFail();
        }
    }

    private function resolveBackend(?string $backendKey): StoredFileStorageBackend
    {
        $backendKey ??= config('file-storage.defaultBackend');
        $configuration = config("file-storage.backends.{$backendKey}");

        if (! is_array($configuration) || ! is_string($configuration['state'] ?? null)) {
            throw new FileStorageIngestionException('The selected file storage backend configuration is unavailable.');
        }

        $backend = StoredFileStorageBackend::query()
            ->where('backendKey', $backendKey)
            ->lockForUpdate()
            ->first();

        if ($backend !== null) {
            return $backend;
        }

        try {
            return StoredFileStorageBackend::query()->create([
                'backendKey' => $backendKey,
                'state' => strtoupper($configuration['state']),
            ]);
        } catch (QueryException) {
            return StoredFileStorageBackend::query()
                ->where('backendKey', $backendKey)
                ->firstOrFail();
        }
    }

    private function stage(Filesystem $disk, string $stagingKey, string $sourcePath): void
    {
        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new FileStorageIngestionException('The file storage source cannot be opened.');
        }

        try {
            if ($disk->writeStream($stagingKey, $stream) !== true) {
                throw new FileStorageIngestionException('The file storage source cannot be staged.');
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{logicalSha256: string, logicalSizeBytes: int, detectedMimeType: string, declaredExtension: ?string}
     */
    private function describe(Filesystem $disk, string $stagingKey, string $sourcePath): array
    {
        $stream = $disk->readStream($stagingKey);

        if (! is_resource($stream)) {
            throw new FileStorageIngestionException('The staged file storage content cannot be read.');
        }

        try {
            $hash = hash_init('sha256');
            $bytes = hash_update_stream($hash, $stream);

            if ($bytes === false) {
                throw new FileStorageIngestionException('The staged file storage content cannot be inspected.');
            }
        } finally {
            fclose($stream);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath);

        if (! is_string($mimeType) || $mimeType === '') {
            throw new FileStorageIngestionException('The file storage content type cannot be detected.');
        }

        $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));

        return [
            'logicalSha256' => hash_final($hash),
            'logicalSizeBytes' => $bytes,
            'detectedMimeType' => $mimeType,
            'declaredExtension' => $extension === '' ? null : substr($extension, 0, 32),
        ];
    }

    private function promote(Filesystem $disk, string $stagingKey, string $storageKey, string $expectedSha256): void
    {
        if ($disk->exists($storageKey)) {
            $stream = $disk->readStream($storageKey);

            if (! is_resource($stream)) {
                throw new FileStorageIngestionException('The existing file storage object cannot be inspected.');
            }

            try {
                $hash = hash_init('sha256');
                $bytes = hash_update_stream($hash, $stream);

                if ($bytes === false) {
                    throw new FileStorageIngestionException('The existing file storage object cannot be inspected.');
                }

                $storedSha256 = hash_final($hash);
            } finally {
                fclose($stream);
            }

            if (! hash_equals($expectedSha256, $storedSha256)) {
                throw new FileStorageIngestionException('The existing file storage object does not match its content hash.');
            }

            $this->deleteIfPresent($disk, $stagingKey);

            return;
        }

        if ($disk->move($stagingKey, $storageKey) !== true) {
            throw new FileStorageIngestionException('The staged file storage content cannot be promoted.');
        }
    }

    private function deleteIfPresent(Filesystem $disk, string $path): void
    {
        try {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable) {
        }
    }

    private function storageKey(string $logicalSha256): string
    {
        return 'objects/sha256/'.substr($logicalSha256, 0, 2).'/'.substr($logicalSha256, 2, 2).'/'.$logicalSha256.'.blob';
    }

    private function result(StoredFileContent $content, StoredFileStorageObject $object, bool $reusedStorageObject): IngestedStoredFileContent
    {
        return new IngestedStoredFileContent(
            contentId: $content->id,
            storageObjectId: $object->id,
            logicalSha256: $content->logicalSha256,
            logicalSizeBytes: $content->logicalSizeBytes,
            detectedMimeType: $content->detectedMimeType,
            declaredExtension: $content->declaredExtension,
            storageKey: $object->storageKey,
            reusedStorageObject: $reusedStorageObject,
        );
    }
}
