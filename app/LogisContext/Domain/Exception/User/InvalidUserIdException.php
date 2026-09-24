<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\User;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidUserIdException extends InvalidValueException
{
    public static function fromValue(int $value): self
    {
        return new self(message: sprintf('"%d" is not a valid user id.', $value));
    }
}
