<?php

namespace App\Services\FileStorage\Drive;

use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\IngestWorkspaceContentRequest;
use App\Contracts\FileStorage\V1\StoreWorkspaceVersionRequest;
use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Domain\FileStorage\Exception\DriveWorkspaceUploadException;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DriveWorkspaceUploadService
{
    public function __construct(
        private readonly FileStorageV1 $fileStorage,
        private readonly DriveWorkspaceCommandService $commands,
        private readonly DriveWorkspaceNameResolver $names,
    ) {}

    /**
     * Ingests every eligible source independently, so one rejected item never creates an active possession.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array<string, mixed>>
     */
    public function upload(User $principal, DriveWorkspaceTarget $target, array $files, ?int $parentFolderId): array
    {
        $configuration = $this->configuration();
        if (count($files) > $configuration['maximumBatchFiles'] || $this->batchBytes($files) > $configuration['maximumBatchBytes']) {
            return array_map(fn (UploadedFile $file, int $index): array => $this->failure($index, $file, 'DRIVE_UPLOAD_LIMIT_EXCEEDED'), $files, array_keys($files));
        }

        return array_map(function (UploadedFile $file, int $index) use ($principal, $target, $parentFolderId, $configuration): array {
            $failure = $this->validate($file, $index, $configuration);
            if ($failure !== null) {
                return $failure;
            }

            try {
                $detectedMimeType = $this->detectedMimeType($file);
                if (! $this->isAllowedMimeType($detectedMimeType, $configuration['allowedMimeTypes'])) {
                    return $this->failure($index, $file, 'DRIVE_UPLOAD_TYPE_NOT_ALLOWED');
                }
                // Refuse an unauthorized destination before bytes reach the storage facade.
                DB::transaction(fn () => $this->commands->lockEditableDestination($principal, $target, $parentFolderId));
                $ingested = $this->fileStorage->ingestWorkspaceContent(new IngestWorkspaceContentRequest((string) $file->getRealPath()));

                return DB::transaction(function () use ($principal, $target, $parentFolderId, $file, $index, $ingested): array {
                    // Re-check under the workspace lock: access can change while content is being staged.
                    $destination = $this->commands->lockEditableDestination($principal, $target, $parentFolderId);
                    $displayName = $this->names->resolve($destination, $parentFolderId, $file->getClientOriginalName());
                    $stored = $this->fileStorage->storeWorkspaceVersion(new StoreWorkspaceVersionRequest(
                        ownerType: $destination->ownerType,
                        ownerId: $destination->ownerId,
                        content: $ingested,
                        displayName: $displayName,
                        workspaceFolderId: $parentFolderId,
                    ));
                    $possession = StoredFilePossession::query()->findOrFail($stored->possessionId);

                    return [
                        'clientIndex' => $index,
                        'state' => 'STORED',
                        'item' => [
                            'id' => $possession->id,
                            'kind' => 'file',
                            'displayName' => $possession->displayName,
                            'parentFolderId' => $possession->idWorkspaceFolder,
                            'logicalSizeBytes' => $stored->logicalSizeBytes,
                            'detectedMimeType' => $stored->detectedMimeType,
                            'modifiedAt' => $possession->updatedAt?->toISOString(),
                        ],
                    ];
                });
            } catch (DriveWorkspaceCommandException $exception) {
                return $this->failure($index, $file, $exception->reasonCode);
            } catch (Throwable $exception) {
                return $this->failure($index, $file, 'DRIVE_UPLOAD_FAILED');
            }
        }, $files, array_keys($files));
    }

    /** @return array{maximumFileBytes: int, maximumBatchFiles: int, maximumBatchBytes: int, allowedMimeTypes: list<string>} */
    private function configuration(): array
    {
        $configuration = config('file-storage.workspaceUpload');
        if (! is_array($configuration)
            || ! is_int($configuration['maximumFileBytes'] ?? null) || $configuration['maximumFileBytes'] < 1
            || ! is_int($configuration['maximumBatchFiles'] ?? null) || $configuration['maximumBatchFiles'] < 1
            || ! is_int($configuration['maximumBatchBytes'] ?? null) || $configuration['maximumBatchBytes'] < 1
            || ! is_array($configuration['allowedMimeTypes'] ?? null)) {
            throw new DriveWorkspaceUploadException('DRIVE_UPLOAD_CONFIGURATION_INVALID');
        }

        return $configuration;
    }

    /** @param array{maximumFileBytes: int, maximumBatchFiles: int, maximumBatchBytes: int, allowedMimeTypes: list<string>} $configuration */
    private function validate(UploadedFile $file, int $index, array $configuration): ?array
    {
        if (! $file->isValid() || $file->getRealPath() === false) {
            return $this->failure($index, $file, 'DRIVE_UPLOAD_INVALID_FILE');
        }
        if ((int) $file->getSize() > $configuration['maximumFileBytes']) {
            return $this->failure($index, $file, 'DRIVE_UPLOAD_LIMIT_EXCEEDED');
        }

        try {
            $this->names->normalize($file->getClientOriginalName());
        } catch (DriveWorkspaceCommandException) {
            return $this->failure($index, $file, 'DRIVE_INVALID_NAME');
        }

        return null;
    }

    /** @param list<UploadedFile> $files */
    private function batchBytes(array $files): int
    {
        return array_sum(array_map(fn (UploadedFile $file): int => max(0, (int) $file->getSize()), $files));
    }

    private function detectedMimeType(UploadedFile $file): string
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
        if (! is_string($mimeType) || $mimeType === '') {
            throw new DriveWorkspaceUploadException('DRIVE_UPLOAD_INVALID_FILE');
        }

        return strtolower($mimeType);
    }

    /** @param list<string> $allowedMimeTypes */
    private function isAllowedMimeType(string $mimeType, array $allowedMimeTypes): bool
    {
        foreach ($allowedMimeTypes as $allowedMimeType) {
            $allowedMimeType = strtolower(trim($allowedMimeType));
            if ($allowedMimeType === '*/*' || $allowedMimeType === $mimeType
                || (str_ends_with($allowedMimeType, '/*') && str_starts_with($mimeType, Str::before($allowedMimeType, '*')))) {
                return true;
            }
        }

        return false;
    }

    /** @return array{clientIndex: int, state: string, error: array{code: string}} */
    private function failure(int $index, UploadedFile $_file, string $code): array
    {
        return ['clientIndex' => $index, 'state' => 'REJECTED', 'error' => ['code' => $code]];
    }
}
