<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Ticket;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidRepairIdException extends InvalidValueException
{
    public static function create(): self
    {
        return new self(message: 'The repair id must have between 1 and 255 characters.');
    }
}
