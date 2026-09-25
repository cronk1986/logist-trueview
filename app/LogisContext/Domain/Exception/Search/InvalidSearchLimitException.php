<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Search;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidSearchLimitException extends InvalidValueException
{
    public static function outOfRange(int $value, int $min, int $max): self
    {
        return new self(message: sprintf('The search limit must be between %d and %d, %d given.', $min, $max, $value));
    }
}
