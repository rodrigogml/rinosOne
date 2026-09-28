<?php

return [
    'auditRetentionDays' => (int) env('AUTHORIZATION_AUDIT_RETENTION_DAYS', 90),
    'decisionCacheSeconds' => (int) env('AUTHORIZATION_DECISION_CACHE_SECONDS', 300),
    'maxGroupNestingDepth' => max(1, (int) env('AUTHORIZATION_MAX_GROUP_NESTING_DEPTH', 5)),
    'maxDelegationDays' => max(1, (int) env('AUTHORIZATION_MAX_DELEGATION_DAYS', 30)),
    // Regression guard for the deterministic SQLite authorization benchmark; not a production SLA.
    'benchmarkMaxMilliseconds' => (int) env('AUTHORIZATION_BENCHMARK_MAX_MILLISECONDS', 1000),
];
