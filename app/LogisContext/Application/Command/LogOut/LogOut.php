<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\LogOut;

final readonly class LogOut
{
    public function __construct(public string $currentTokenHash)
    {
    }
}
