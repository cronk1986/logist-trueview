<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Search;

use Logis\LogisContext\Domain\Exception\Search\InvalidSearchLimitException;

final readonly class SearchLimit
{
    public const MIN = 1;
    public const MAX = 50;
    public const DEFAULT = 5;

    private function __construct(private int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidSearchLimitException::outOfRange(value: $value, min: self::MIN, max: self::MAX);
        }

        return new self(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isExceededBy(int $total): bool
    {
        return $total > $this->value;
    }
}
