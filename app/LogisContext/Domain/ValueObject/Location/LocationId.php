<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Location;

use Logis\LogisContext\Domain\Exception\Location\InvalidLocationIdException;

final readonly class LocationId
{
    private function __construct(private int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw InvalidLocationIdException::fromValue(value: $value);
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
