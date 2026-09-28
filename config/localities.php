<?php

return [
    'ibge' => [
        'base_url' => env('LOCALITIES_IBGE_BASE_URL', 'https://servicodados.ibge.gov.br/api/v1/localidades'),
        'timeout_seconds' => (int) env('LOCALITIES_IBGE_TIMEOUT_SECONDS', 30),
        'ca_bundle' => env('LOCALITIES_IBGE_CA_BUNDLE'),
    ],

    'ibge_territory_maintenance' => [
        'lock_seconds' => (int) env('LOCALITIES_IBGE_TERRITORY_MAINTENANCE_LOCK_SECONDS', 7200),
        'success_interval_months' => (int) env('LOCALITIES_IBGE_TERRITORY_MAINTENANCE_SUCCESS_INTERVAL_MONTHS', 1),
        'failure_retry_delay_hours' => (int) env('LOCALITIES_IBGE_TERRITORY_MAINTENANCE_FAILURE_RETRY_DELAY_HOURS', 6),
    ],

    'postal' => [
        'ca_bundle' => env('LOCALITIES_POSTAL_CA_BUNDLE'),
        'via_cep' => [
            'enabled' => (bool) env('LOCALITIES_POSTAL_VIA_CEP_ENABLED', true),
            'base_url' => env('LOCALITIES_POSTAL_VIA_CEP_BASE_URL', 'https://viacep.com.br/ws'),
            'timeout_seconds' => (int) env('LOCALITIES_POSTAL_VIA_CEP_TIMEOUT_SECONDS', 10),
        ],
        'brasil_api' => [
            'enabled' => (bool) env('LOCALITIES_POSTAL_BRASIL_API_ENABLED', true),
            'base_url' => env('LOCALITIES_POSTAL_BRASIL_API_BASE_URL', 'https://brasilapi.com.br/api/cep/v2'),
            'timeout_seconds' => (int) env('LOCALITIES_POSTAL_BRASIL_API_TIMEOUT_SECONDS', 10),
        ],
        'refresh_state_ttl_seconds' => (int) env('LOCALITIES_POSTAL_REFRESH_STATE_TTL_SECONDS', 300),
        'enrichment_lock_seconds' => (int) env('LOCALITIES_POSTAL_ENRICHMENT_LOCK_SECONDS', 120),
        'poll_after_milliseconds' => (int) env('LOCALITIES_POSTAL_POLL_AFTER_MILLISECONDS', 1000),
    ],
];
