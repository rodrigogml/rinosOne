<?php

return [
    'auditRetentionDays' => (int) env('PERSON_AUDIT_RETENTION_DAYS', 90),
    // Aggregate API metrics contain no tenant, user, Person or request values.
    'metricsRetentionMinutes' => (int) env('PERSON_METRICS_RETENTION_MINUTES', 15),
    'performanceAlertP95Milliseconds' => (int) env('PERSON_PERFORMANCE_ALERT_P95_MILLISECONDS', 2000),
    'performanceAlertWindowMinutes' => (int) env('PERSON_PERFORMANCE_ALERT_WINDOW_MINUTES', 5),
];
