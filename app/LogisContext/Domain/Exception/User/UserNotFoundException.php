<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\User;

use Logis\SharedContext\Domain\Exception\NotFoundException;

final class UserNotFoundException extends NotFoundException
{
    public static function withId(int $id): self
    {
        return new self(message: sprintf('User %d not found.', $id));
    }
}
