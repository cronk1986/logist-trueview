<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\RotateAccessToken;

final readonly class RotateAccessToken
{
    public function __construct(
        public string $currentTokenHash,
        public string $newTokenHash,
        public string $ipAddress,
        public string $userAgent,
    ) {
    }
}
