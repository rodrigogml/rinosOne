<?php

$additionalBackends = json_decode((string) env('FILE_STORAGE_BACKEND_DEFINITIONS', '{}'), true);
$compressionRules = json_decode((string) env('FILE_COMPRESSION_RULES', ''), true);

return [
    'defaultBackend' => env('FILE_STORAGE_DEFAULT_BACKEND', 'local-private'),

    'backends' => array_replace([
        'local-private' => [
            'disk' => env('FILE_STORAGE_LOCAL_PRIVATE_DISK', 'file-private'),
            'state' => env('FILE_STORAGE_LOCAL_PRIVATE_STATE', 'ACTIVE'),
        ],
    ], is_array($additionalBackends) ? $additionalBackends : []),

    'retention' => [
        'trashDays' => (int) env('FILE_TRASH_RETENTION_DAYS', 30),
        'backupDays' => (int) env('FILE_BACKUP_RETENTION_DAYS', 60),
        'technicalDays' => (int) env('FILE_TECHNICAL_RETENTION_DAYS', 60),
        'orphanDays' => (int) env('FILE_ORPHAN_RETENTION_DAYS', 60),
    ],

    'compression' => [
        'rules' => is_array($compressionRules) ? $compressionRules : [
            [
                'mimeTypes' => ['text/*', 'application/json', 'application/xml'],
                'extensions' => ['txt', 'csv', 'json', 'xml', 'log'],
                'encoding' => 'GZIP',
            ],
        ],
        'reprocessIntervalMinutes' => (int) env('FILE_COMPRESSION_REPROCESS_INTERVAL_MINUTES', 60),
    ],

    'workspaceUpload' => [
        'maximumFileBytes' => (int) env('DRIVE_UPLOAD_MAXIMUM_FILE_BYTES', 104857600),
        'maximumBatchFiles' => (int) env('DRIVE_UPLOAD_MAXIMUM_BATCH_FILES', 20),
        'maximumBatchBytes' => (int) env('DRIVE_UPLOAD_MAXIMUM_BATCH_BYTES', 524288000),
        'allowedMimeTypes' => array_values(array_filter(array_map('trim', explode(',', (string) env('DRIVE_UPLOAD_ALLOWED_MIME_TYPES', '*/*'))))),
        'temporaryRetentionMinutes' => (int) env('DRIVE_UPLOAD_TEMPORARY_RETENTION_MINUTES', 60),
    ],

    'workspaceExport' => [
        'lifetimeMinutes' => (int) env('DRIVE_EXPORT_LIFETIME_MINUTES', 60),
        'maximumItems' => (int) env('DRIVE_EXPORT_MAXIMUM_ITEMS', 100),
        'maximumBytes' => (int) env('DRIVE_EXPORT_MAXIMUM_BYTES', 1073741824),
        'cleanupIntervalMinutes' => (int) env('DRIVE_EXPORT_CLEANUP_INTERVAL_MINUTES', 15),
    ],

    // Logical transfers only create database relations; the underlying deduplicated bytes stay in place.
    'workspaceTransfer' => [
        // A worker renews this lease while executing. Expiry releases the affected branches safely.
        'leaseMinutes' => (int) env('DRIVE_TRANSFER_LEASE_MINUTES', 15),
        'heartbeatSeconds' => (int) env('DRIVE_TRANSFER_HEARTBEAT_SECONDS', 30),
        'maximumAttempts' => (int) env('DRIVE_TRANSFER_MAXIMUM_ATTEMPTS', 3),
        'maximumItems' => (int) env('DRIVE_TRANSFER_MAXIMUM_ITEMS', 100),
        'maximumTreeDepth' => (int) env('DRIVE_TRANSFER_MAXIMUM_TREE_DEPTH', 32),
        // Scheduler interval for recovering abandoned jobs and deleting stale reservations.
        'recoveryIntervalMinutes' => (int) env('DRIVE_TRANSFER_RECOVERY_INTERVAL_MINUTES', 5),
        'terminalRetentionDays' => (int) env('DRIVE_TRANSFER_TERMINAL_RETENTION_DAYS', 30),
    ],

    'maintenance' => [
        'retentionPurgeIntervalMinutes' => (int) env('FILE_STORAGE_RETENTION_PURGE_INTERVAL_MINUTES', 60),
        'reconciliationIntervalMinutes' => (int) env('FILE_STORAGE_RECONCILIATION_INTERVAL_MINUTES', 60),
    ],
];
