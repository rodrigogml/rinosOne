<?php

return [
    'historyRetentionDays' => (int) env('MAINTENANCE_HISTORY_RETENTION_DAYS', 90),
    'administrativeAuditRetentionDays' => (int) env('MAINTENANCE_ADMINISTRATIVE_AUDIT_RETENTION_DAYS', 90),
];
