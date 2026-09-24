<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\AccessToken;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidTokenHashException extends InvalidValueException
{
    public static function create(): self
    {
        return new self(message: 'The access token hash is not valid.');
    }
}
