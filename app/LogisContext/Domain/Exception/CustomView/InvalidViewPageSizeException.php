<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidViewPageSizeException extends InvalidValueException
{
    public static function outOfRange(int $value, int $min, int $max): self
    {
        return new self(message: sprintf('The page size must be between %d and %d, %d given.', $min, $max, $value));
    }
}
