<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidViewColumnsException extends InvalidValueException
{
    public static function empty(): self
    {
        return new self(message: 'A view needs at least one column.');
    }

    public static function notAColumnName(): self
    {
        return new self(message: 'Every view column must be a non-empty string.');
    }

    public static function duplicated(string $column): self
    {
        return new self(message: sprintf('The column "%s" appears more than once in the view.', $column));
    }

    public static function unknown(string $column, string $viewType): self
    {
        return new self(message: sprintf('"%s" is not a column of %s views.', $column, $viewType));
    }
}
