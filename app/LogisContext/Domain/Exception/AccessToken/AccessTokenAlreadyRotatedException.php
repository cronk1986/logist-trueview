<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\AccessToken;

use Logis\SharedContext\Domain\Exception\ConflictException;

final class AccessTokenAlreadyRotatedException extends ConflictException
{
    public static function create(): self
    {
        return new self(message: 'The access token has already been rotated.');
    }
}
