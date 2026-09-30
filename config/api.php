<?php

return [
    'pagination' => [
        'defaultPerPage' => (int) env('API_PAGINATION_DEFAULT_PER_PAGE', 50),
        'maximumPerPage' => (int) env('API_PAGINATION_MAXIMUM_PER_PAGE', 200),
    ],
    'request' => [
        'maximumJsonBytes' => (int) env('API_REQUEST_MAXIMUM_JSON_BYTES', 1048576),
    ],
    'rateLimit' => [
        'authenticatedRequestsPerMinute' => (int) env('API_AUTHENTICATED_REQUESTS_PER_MINUTE', 120),
    ],
    'idempotency' => [
        'retentionHours' => (int) env('API_IDEMPOTENCY_RETENTION_HOURS', 24),
    ],
];
