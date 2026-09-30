<?php

use App\Jobs\FileStorage\PurgeExpiredFilePossessions;
use App\Jobs\FileStorage\PurgeExpiredWorkspaceExports;
use App\Jobs\FileStorage\PurgeRetainedFileStorageObjects;
use App\Jobs\FileStorage\PurgeRetainedFileVersions;
use App\Jobs\FileStorage\ReconcileFileStorageObjects;
use App\Jobs\FileStorage\RecoverExpiredWorkspaceTransfers;
use App\Jobs\FileStorage\ReprocessFileStorageCompression;
use App\Jobs\Tenant\DiscoverTenantSchemaUpdates;
use App\Services\Maintenance\FinancialInstitutionMaintenanceService;
use App\Services\Maintenance\IbgeTerritoryMaintenanceService;
use App\Services\Maintenance\PersonAuditRetentionMaintenanceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$cleanupIntervalMinutes = config('access.authentication.expiredChallengeCleanupIntervalMinutes');

if ($cleanupIntervalMinutes < 1 || $cleanupIntervalMinutes > 59) {
    throw new LogicException('ACCESS_EXPIRED_CHALLENGE_CLEANUP_INTERVAL_MINUTES must be between 1 and 59.');
}

Schedule::command('access:purge-expired-challenges')
    ->cron("*/{$cleanupIntervalMinutes} * * * *");

Schedule::command('authorization:purge-audit-events')->daily();
Schedule::command('authorization:expire-access-requests')->everyMinute();
Schedule::command('authorization:expire-service-identities')->everyMinute();

Schedule::call(static fn () => app(FinancialInstitutionMaintenanceService::class)->synchronizeScheduled())
    ->daily()
    ->name('maintenance.financial-institution-catalog');

Schedule::call(static fn () => app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue())
    ->hourly()
    ->name('maintenance.locality-ibge-territory-catalog');

Schedule::call(static fn () => app(PersonAuditRetentionMaintenanceService::class)->purgeScheduled())
    ->daily()
    ->name('maintenance.person-audit-retention');

Schedule::job(new DiscoverTenantSchemaUpdates)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->name('tenant-schema-update-discovery');

$fileStorageRetentionPurgeInterval = config('file-storage.maintenance.retentionPurgeIntervalMinutes');

if ($fileStorageRetentionPurgeInterval < 1 || $fileStorageRetentionPurgeInterval > 60) {
    throw new LogicException('FILE_STORAGE_RETENTION_PURGE_INTERVAL_MINUTES must be between 1 and 60.');
}

$fileStorageRetentionPurgeExpression = $fileStorageRetentionPurgeInterval === 60
    ? '0 * * * *'
    : "*/{$fileStorageRetentionPurgeInterval} * * * *";

Schedule::job(new PurgeExpiredFilePossessions)->cron($fileStorageRetentionPurgeExpression);
$workspaceExportCleanupInterval = config('file-storage.workspaceExport.cleanupIntervalMinutes');
if ($workspaceExportCleanupInterval < 1 || $workspaceExportCleanupInterval > 60) {
    throw new LogicException('DRIVE_EXPORT_CLEANUP_INTERVAL_MINUTES must be between 1 and 60.');
}
Schedule::job(new PurgeExpiredWorkspaceExports)->cron($workspaceExportCleanupInterval === 60 ? '0 * * * *' : "*/{$workspaceExportCleanupInterval} * * * *");
Schedule::job(new PurgeRetainedFileVersions)->cron($fileStorageRetentionPurgeExpression);
Schedule::job(new PurgeRetainedFileStorageObjects)->cron($fileStorageRetentionPurgeExpression);

$fileStorageReconciliationInterval = config('file-storage.maintenance.reconciliationIntervalMinutes');

if ($fileStorageReconciliationInterval < 1 || $fileStorageReconciliationInterval > 60) {
    throw new LogicException('FILE_STORAGE_RECONCILIATION_INTERVAL_MINUTES must be between 1 and 60.');
}

$fileStorageReconciliationExpression = $fileStorageReconciliationInterval === 60
    ? '0 * * * *'
    : "*/{$fileStorageReconciliationInterval} * * * *";

Schedule::job(new ReconcileFileStorageObjects)->cron($fileStorageReconciliationExpression);

$fileStorageCompressionReprocessInterval = config('file-storage.compression.reprocessIntervalMinutes');

if ($fileStorageCompressionReprocessInterval < 1 || $fileStorageCompressionReprocessInterval > 60) {
    throw new LogicException('FILE_COMPRESSION_REPROCESS_INTERVAL_MINUTES must be between 1 and 60.');
}

$fileStorageCompressionReprocessExpression = $fileStorageCompressionReprocessInterval === 60
    ? '0 * * * *'
    : "*/{$fileStorageCompressionReprocessInterval} * * * *";

Schedule::job(new ReprocessFileStorageCompression)->cron($fileStorageCompressionReprocessExpression);

$workspaceTransferRecoveryInterval = config('file-storage.workspaceTransfer.recoveryIntervalMinutes');
if ($workspaceTransferRecoveryInterval < 1 || $workspaceTransferRecoveryInterval > 60) {
    throw new LogicException('DRIVE_TRANSFER_RECOVERY_INTERVAL_MINUTES must be between 1 and 60.');
}
Schedule::job(new RecoverExpiredWorkspaceTransfers)
    ->cron($workspaceTransferRecoveryInterval === 60 ? '0 * * * *' : "*/{$workspaceTransferRecoveryInterval} * * * *")
    ->withoutOverlapping(5)
    ->name('file-storage.workspace-transfer-recovery');
