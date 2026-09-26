<?php

declare(strict_types=1);

return [
    'cache_ttl_seconds' => (int) env('VIEWS_CACHE_TTL_SECONDS', 300),
    'execution_timeout_ms' => (int) env('VIEWS_EXECUTION_TIMEOUT_MS', 5000),
];
