<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\User;

use Logis\SharedContext\Domain\Exception\UnauthorizedException;

final class InvalidCredentialsException extends UnauthorizedException
{
    public static function create(): self
    {
        return new self(message: 'Invalid credentials');
    }
}
