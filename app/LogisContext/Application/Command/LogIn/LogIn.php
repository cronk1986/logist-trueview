<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\LogIn;

final readonly class LogIn
{
    public function __construct(
        public string $email,
        public string $password,
        public string $tokenHash,
        public string $ipAddress,
        public string $userAgent,
    ) {
    }
}
