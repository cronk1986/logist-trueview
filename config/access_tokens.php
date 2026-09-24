<?php

declare(strict_types=1);

return [
    'ttl_minutes' => (int) env('ACCESS_TOKEN_TTL_MINUTES', 60),
    'grace_seconds' => (int) env('ACCESS_TOKEN_GRACE_SECONDS', 10),
    'prune_hours' => (int) env('ACCESS_TOKEN_PRUNE_HOURS', 24),
];
