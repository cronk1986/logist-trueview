<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Ticket;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidIncidentNumberException extends InvalidValueException
{
    public static function create(): self
    {
        return new self(message: 'The incident number must have between 1 and 255 characters.');
    }
}
