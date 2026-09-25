<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Customer;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidCustomerIdException extends InvalidValueException
{
    public static function fromValue(int $value): self
    {
        return new self(message: sprintf('"%d" is not a valid customer id.', $value));
    }
}
