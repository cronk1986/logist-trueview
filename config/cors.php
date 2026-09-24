<?php

declare(strict_types=1);

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:8080')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Refreshed-Token'],
    'max_age' => 0,
    'supports_credentials' => false,
];
