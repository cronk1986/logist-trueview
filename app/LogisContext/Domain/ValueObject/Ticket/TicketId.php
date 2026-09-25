<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Ticket;

use Logis\LogisContext\Domain\Exception\Ticket\InvalidTicketIdException;

final readonly class TicketId
{
    private function __construct(private int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw InvalidTicketIdException::fromValue(value: $value);
        }

        return new self(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
