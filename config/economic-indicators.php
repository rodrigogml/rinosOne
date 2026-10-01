<?php

return [
    'bcb' => [
        'sgs_base_url' => env('BCB_SGS_BASE_URL', 'https://api.bcb.gov.br/dados/serie'),
        'ptax_base_url' => env('BCB_PTAX_BASE_URL', 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata'),
        'timeout_seconds' => (int) env('BCB_TIMEOUT_SECONDS', 30),
        'ca_bundle' => env('BCB_CA_BUNDLE'),
        'use_native_ca' => (bool) env('BCB_USE_NATIVE_CA', PHP_OS_FAMILY === 'Windows'),
        'force_ipv4' => (bool) env('BCB_FORCE_IPV4', true),
    ],
    'maintenance_lock_seconds' => (int) env('ECONOMIC_INDICATOR_MAINTENANCE_LOCK_SECONDS', 7200),
    'overlap_days' => (int) env('ECONOMIC_INDICATOR_OVERLAP_DAYS', 7),
    'historical_chunk_days' => (int) env('ECONOMIC_INDICATOR_HISTORICAL_CHUNK_DAYS', 3650),
    'enabled_series' => array_values(array_filter(array_map(
        static fn (string $code): string => trim($code),
        explode(',', (string) env('ECONOMIC_INDICATOR_ENABLED_SERIES', '')),
    ))),
];
