<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidViewFilterException extends InvalidValueException
{
    public static function notAFilter(): self
    {
        return new self(message: 'Every view filter must be an object with a column, an operator and a value.');
    }

    public static function missing(string $key): self
    {
        return new self(message: sprintf('A view filter needs a non-empty "%s".', $key));
    }

    public static function invalidValue(string $column): self
    {
        return new self(message: sprintf('The value of the filter on "%s" must be a scalar, null or a list of scalars.', $column));
    }
}
