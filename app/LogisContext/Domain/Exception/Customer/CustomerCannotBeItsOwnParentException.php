<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Customer;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class CustomerCannotBeItsOwnParentException extends InvalidValueException
{
    public static function withId(int $id): self
    {
        return new self(message: sprintf('Customer %d cannot be its own parent.', $id));
    }
}
