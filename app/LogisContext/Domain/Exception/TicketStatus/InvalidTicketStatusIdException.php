<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\TicketStatus;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidTicketStatusIdException extends InvalidValueException
{
    public static function fromValue(int $value): self
    {
        return new self(message: sprintf('"%d" is not a valid ticket status id.', $value));
    }
}
