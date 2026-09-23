<?php

return [
    'schemas' => [
        'core' => env('RINOS_CORE_DATABASE', 'rinosone'),
        'tenantPrefix' => env('RINOS_TENANT_DATABASE_PREFIX', 'rinosone_'),
    ],

    'authentication' => [
        'emailChallengeLifetimeMinutes' => (int) env('ACCESS_EMAIL_CHALLENGE_LIFETIME_MINUTES', 10),
        'emailEmissionLimit' => (int) env('ACCESS_EMAIL_EMISSION_LIMIT', 3),
        'emailEmissionWindowMinutes' => (int) env('ACCESS_EMAIL_EMISSION_WINDOW_MINUTES', 15),
        'originEmissionLimit' => (int) env('ACCESS_ORIGIN_EMISSION_LIMIT', 10),
        'originEmissionWindowMinutes' => (int) env('ACCESS_ORIGIN_EMISSION_WINDOW_MINUTES', 60),
        'userEmissionLimit' => (int) env('ACCESS_USER_EMISSION_LIMIT', 3),
        'userEmissionWindowMinutes' => (int) env('ACCESS_USER_EMISSION_WINDOW_MINUTES', 15),
        'codeAttemptLimit' => (int) env('ACCESS_CODE_ATTEMPT_LIMIT', 5),
        'codeAttemptWindowMinutes' => (int) env('ACCESS_CODE_ATTEMPT_WINDOW_MINUTES', 10),
        'codeMaximumAttempts' => (int) env('ACCESS_CODE_MAXIMUM_ATTEMPTS', 3),
        'passwordAttemptLimit' => (int) env('ACCESS_PASSWORD_ATTEMPT_LIMIT', 5),
        'passwordAttemptWindowMinutes' => (int) env('ACCESS_PASSWORD_ATTEMPT_WINDOW_MINUTES', 15),
        'userPasswordAttemptLimit' => (int) env('ACCESS_USER_PASSWORD_ATTEMPT_LIMIT', 5),
        'userPasswordAttemptWindowMinutes' => (int) env('ACCESS_USER_PASSWORD_ATTEMPT_WINDOW_MINUTES', 15),
        'temporaryBlockMinutes' => (int) env('ACCESS_TEMPORARY_BLOCK_MINUTES', 15),
        'sessionInactivityTimeoutMinutes' => (int) env('ACCESS_SESSION_INACTIVITY_TIMEOUT_MINUTES', 0),
        'persistentLoginLifetimeDays' => (int) env('ACCESS_PERSISTENT_LOGIN_LIFETIME_DAYS', 0),
        'persistentCookieName' => env('ACCESS_PERSISTENT_COOKIE_NAME', 'rinosone-persistent-authentication'),
        'securityLogRetentionDays' => (int) env('ACCESS_SECURITY_LOG_RETENTION_DAYS', 30),
        'expiredChallengeCleanupIntervalMinutes' => (int) env('ACCESS_EXPIRED_CHALLENGE_CLEANUP_INTERVAL_MINUTES', 15),
    ],
];
