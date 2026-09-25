<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Asset;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidSerialNumberException extends InvalidValueException
{
    public static function create(): self
    {
        return new self(message: 'The serial number must have between 1 and 255 characters.');
    }
}
