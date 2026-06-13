<?php

return [
    'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'FreelanceFlow')),
    'window' => (int) env('TWO_FACTOR_WINDOW', 1),
    'recovery_code_count' => (int) env('TWO_FACTOR_RECOVERY_CODE_COUNT', 8),
];
