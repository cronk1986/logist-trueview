<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Customer;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidErpIdException extends InvalidValueException
{
    public static function create(): self
    {
        return new self(message: 'The ERP id must have between 1 and 255 characters.');
    }
}
