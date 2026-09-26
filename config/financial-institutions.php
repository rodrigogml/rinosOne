<?php

return [
    'bcb' => [
        'base_url' => env('BCB_BASE_URL', 'https://olinda.bcb.gov.br/olinda/servico/BcBase/versao/v2/odata'),
        'timeout_seconds' => (int) env('BCB_TIMEOUT_SECONDS', 30),
    ],

    'maintenance_lock_seconds' => (int) env('FINANCIAL_INSTITUTION_MAINTENANCE_LOCK_SECONDS', 7200),
];
