<?php

namespace App\Infrastructure\FileStorage;

use LogicException;

class FileStorageConfigurationValidator
{
    /**
     * Validates the deployment configuration required before private file storage can accept writes.
     *
     * @throws LogicException when a configured backend, retention period, or maintenance interval is invalid.
     */
    public function validate(): void
    {
        $backends = config('file-storage.backends');
        $defaultBackend = config('file-storage.defaultBackend');
        $retention = config('file-storage.retention');

        if (! is_array($backends) || ! is_string($defaultBackend) || ! isset($backends[$defaultBackend])) {
            throw new LogicException('The default file storage backend configuration is invalid.');
        }

        foreach ($backends as $backendKey => $backend) {
            if (! is_string($backendKey) || $backendKey === '' || ! is_array($backend)) {
                throw new LogicException('A file storage backend definition is invalid.');
            }

            $disk = $backend['disk'] ?? null;
            $state = $backend['state'] ?? null;

            if (! is_string($disk) || $disk === '' || ! is_string($state) || ! in_array(strtoupper($state), ['ACTIVE', 'READ_ONLY', 'INACTIVE'], true)) {
                throw new LogicException("The file storage backend [{$backendKey}] configuration is invalid.");
            }

            if (! is_array(config("filesystems.disks.{$disk}"))) {
                throw new LogicException("The file storage backend [{$backendKey}] references an unavailable disk.");
            }
        }

        if (! is_array($retention)
            || ! $this->isPositiveInteger($retention['trashDays'] ?? null)
            || ! $this->isPositiveInteger($retention['backupDays'] ?? null)
            || ! $this->isPositiveInteger($retention['technicalDays'] ?? null)
            || ! $this->isPositiveInteger($retention['orphanDays'] ?? null)) {
            throw new LogicException('The file storage retention configuration is invalid.');
        }

        if ($retention['technicalDays'] < $retention['backupDays']) {
            throw new LogicException('FILE_TECHNICAL_RETENTION_DAYS must not be lower than FILE_BACKUP_RETENTION_DAYS.');
        }

        $workspaceUpload = config('file-storage.workspaceUpload');
        if (! is_array($workspaceUpload)
            || ! $this->isPositiveInteger($workspaceUpload['maximumFileBytes'] ?? null)
            || ! $this->isPositiveInteger($workspaceUpload['maximumBatchFiles'] ?? null)
            || ! $this->isPositiveInteger($workspaceUpload['maximumBatchBytes'] ?? null)
            || ! $this->isPositiveInteger($workspaceUpload['temporaryRetentionMinutes'] ?? null)
            || ! is_array($workspaceUpload['allowedMimeTypes'] ?? null)
            || $workspaceUpload['allowedMimeTypes'] === []) {
            throw new LogicException('The Rinos Drive upload configuration is invalid.');
        }

        $workspaceExport = config('file-storage.workspaceExport');
        if (! is_array($workspaceExport)
            || ! $this->isPositiveInteger($workspaceExport['lifetimeMinutes'] ?? null)
            || ! $this->isPositiveInteger($workspaceExport['maximumItems'] ?? null)
            || ! $this->isPositiveInteger($workspaceExport['maximumBytes'] ?? null)
            || ! $this->isPositiveInteger($workspaceExport['cleanupIntervalMinutes'] ?? null)
            || $workspaceExport['cleanupIntervalMinutes'] > 60) {
            throw new LogicException('The Rinos Drive export configuration is invalid.');
        }

        $compressionRules = config('file-storage.compression.rules');

        if (! is_array($compressionRules)) {
            throw new LogicException('The file storage compression rules configuration is invalid.');
        }

        foreach ($compressionRules as $rule) {
            if (! is_array($rule)
                || ! in_array($rule['encoding'] ?? null, ['GZIP'], true)
                || (! is_array($rule['mimeTypes'] ?? null) && ! is_array($rule['extensions'] ?? null))) {
                throw new LogicException('A file storage compression rule is invalid.');
            }
        }

        $interval = config('file-storage.compression.reprocessIntervalMinutes');

        if (! $this->isPositiveInteger($interval) || $interval > 60) {
            throw new LogicException('FILE_COMPRESSION_REPROCESS_INTERVAL_MINUTES must be between 1 and 60.');
        }

        $retentionPurgeInterval = config('file-storage.maintenance.retentionPurgeIntervalMinutes');

        if (! $this->isPositiveInteger($retentionPurgeInterval) || $retentionPurgeInterval > 60) {
            throw new LogicException('FILE_STORAGE_RETENTION_PURGE_INTERVAL_MINUTES must be between 1 and 60.');
        }

        $reconciliationInterval = config('file-storage.maintenance.reconciliationIntervalMinutes');

        if (! $this->isPositiveInteger($reconciliationInterval) || $reconciliationInterval > 60) {
            throw new LogicException('FILE_STORAGE_RECONCILIATION_INTERVAL_MINUTES must be between 1 and 60.');
        }
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }
}
