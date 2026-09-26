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

    'maintenance' => [
        'retentionPurgeIntervalMinutes' => (int) env('FILE_STORAGE_RETENTION_PURGE_INTERVAL_MINUTES', 60),
        'reconciliationIntervalMinutes' => (int) env('FILE_STORAGE_RECONCILIATION_INTERVAL_MINUTES', 60),
    ],
];
