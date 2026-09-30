<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global catalog revalidation
    |--------------------------------------------------------------------------
    |
    | Zero checks the global migration history on every request. A positive
    | value permits a compatible result to be retained by the application
    | process for the configured number of seconds.
    |
    */
    'globalRevalidationSeconds' => (int) env('SCHEMA_COMPATIBILITY_GLOBAL_REVALIDATION_SECONDS', 0),

    'tenantUpdateRetentionDays' => (int) env('SCHEMA_COMPATIBILITY_TENANT_UPDATE_RETENTION_DAYS', 90),

    'tenantMigrationPath' => database_path('migrations/tenant'),
];
