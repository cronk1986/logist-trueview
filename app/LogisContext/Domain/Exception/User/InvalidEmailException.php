<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\User;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidEmailException extends InvalidValueException
{
    public static function create(): self
    {
        return new self(message: 'The email address is not valid.');
    }
}
