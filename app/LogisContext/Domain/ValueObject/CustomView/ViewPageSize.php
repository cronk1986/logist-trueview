<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewPageSizeException;

final readonly class ViewPageSize
{
    public const MIN = 1;
    public const MAX = 1000;
    public const DEFAULT = 50;

    private function __construct(private int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidViewPageSizeException::outOfRange(value: $value, min: self::MIN, max: self::MAX);
        }

        return new self(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }
}
