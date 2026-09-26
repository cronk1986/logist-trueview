<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\AccessToken;

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
