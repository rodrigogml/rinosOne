<?php

namespace Tests\Unit;

use App\Infrastructure\FileStorage\FileStorageConfigurationValidator;
use LogicException;
use Tests\TestCase;

class FileStorageConfigurationValidatorTest extends TestCase
{
    public function test_it_accepts_the_default_private_storage_policy(): void
    {
        app(FileStorageConfigurationValidator::class)->validate();

        $this->assertSame('local-private', config('file-storage.defaultBackend'));
        $this->assertSame('file-private', config('file-storage.backends.local-private.disk'));
        $this->assertSame(30, config('file-storage.retention.trashDays'));
        $this->assertSame(60, config('file-storage.retention.technicalDays'));
        $this->assertSame(60, config('file-storage.maintenance.retentionPurgeIntervalMinutes'));
        $this->assertSame(60, config('file-storage.maintenance.reconciliationIntervalMinutes'));
    }

    public function test_it_rejects_technical_retention_shorter_than_backup_retention(): void
    {
        config(['file-storage.retention.backupDays' => 61]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('FILE_TECHNICAL_RETENTION_DAYS must not be lower than FILE_BACKUP_RETENTION_DAYS.');

        app(FileStorageConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_a_backend_that_does_not_reference_a_filesystem_disk(): void
    {
        config(['file-storage.backends.local-private.disk' => 'missing-private-disk']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('references an unavailable disk');

        app(FileStorageConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_an_invalid_retention_purge_interval(): void
    {
        config(['file-storage.maintenance.retentionPurgeIntervalMinutes' => 61]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('FILE_STORAGE_RETENTION_PURGE_INTERVAL_MINUTES must be between 1 and 60.');

        app(FileStorageConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_an_invalid_reconciliation_interval(): void
    {
        config(['file-storage.maintenance.reconciliationIntervalMinutes' => 61]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('FILE_STORAGE_RECONCILIATION_INTERVAL_MINUTES must be between 1 and 60.');

        app(FileStorageConfigurationValidator::class)->validate();
    }
}
