<?php

namespace App\Infrastructure\FileStorage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class FileStorageBackendResolver
{
    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly FileStorageConfigurationValidator $configurationValidator,
    ) {}

    /**
     * Resolves a configured private filesystem backend without exposing its physical location.
     *
     * @throws LogicException when the backend is unknown or not available for its requested use.
     */
    public function disk(?string $backendKey = null): Filesystem
    {
        $backend = $this->backend($backendKey);

        return $this->filesystems->disk($backend['disk']);
    }

    /**
     * Confirms that an active backend accepts a private write and cleans the probe afterwards.
     *
     * @throws LogicException when the backend is not active or the probe cannot be written safely.
     */
    public function ensureWritable(?string $backendKey = null): void
    {
        $backend = $this->backend($backendKey);

        if (strtoupper($backend['state']) !== 'ACTIVE') {
            throw new LogicException('The selected file storage backend is not available for writing.');
        }

        $disk = $this->filesystems->disk($backend['disk']);
        $probePath = '.file-storage-health/'.Str::uuid().'.probe';

        try {
            if ($disk->put($probePath, '') !== true) {
                throw new LogicException('The selected file storage backend does not accept writes.');
            }
        } catch (Throwable $exception) {
            if ($exception instanceof LogicException) {
                throw $exception;
            }

            throw new LogicException('The selected file storage backend does not accept writes.', previous: $exception);
        } finally {
            try {
                $disk->delete($probePath);
            } catch (Throwable) {
            }
        }
    }

    /**
     * @return array{disk: string, state: string}
     *
     * @throws LogicException when the requested backend cannot be resolved from configuration.
     */
    private function backend(?string $backendKey): array
    {
        $this->configurationValidator->validate();

        $backendKey ??= config('file-storage.defaultBackend');
        $backend = config("file-storage.backends.{$backendKey}");

        if (! is_array($backend) || ! is_string($backend['disk'] ?? null) || ! is_string($backend['state'] ?? null)) {
            throw new LogicException('The selected file storage backend configuration is unavailable.');
        }

        return [
            'disk' => $backend['disk'],
            'state' => $backend['state'],
        ];
    }
}
