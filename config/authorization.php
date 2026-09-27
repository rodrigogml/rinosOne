<?php

return [
    'auditRetentionDays' => (int) env('AUTHORIZATION_AUDIT_RETENTION_DAYS', 90),
    'decisionCacheSeconds' => (int) env('AUTHORIZATION_DECISION_CACHE_SECONDS', 300),
];
