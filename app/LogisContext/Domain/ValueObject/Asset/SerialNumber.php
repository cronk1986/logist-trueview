<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Asset;

use Logis\LogisContext\Domain\Exception\Asset\InvalidSerialNumberException;

final readonly class SerialNumber
{
    private const MAX_LENGTH = 255;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim(string: $value);

        if ($trimmed === '' || mb_strlen(string: $trimmed) > self::MAX_LENGTH) {
            throw InvalidSerialNumberException::create();
        }

        return new self(value: $trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
