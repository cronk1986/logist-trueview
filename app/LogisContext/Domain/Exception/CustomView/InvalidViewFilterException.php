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

    public static function unknownOperator(string $column, string $operator): self
    {
        return new self(message: sprintf('The filter on "%s" uses the unknown operator "%s".', $column, $operator));
    }

    public static function tooMany(int $max): self
    {
        return new self(message: sprintf('A view can have at most %d filters.', $max));
    }

    public static function unknownField(string $column, string $viewType): self
    {
        return new self(message: sprintf('"%s" is not a filterable field of %s views.', $column, $viewType));
    }

    public static function operatorNotAllowed(string $column, string $operator): self
    {
        return new self(message: sprintf('The operator "%s" cannot be used on "%s".', $operator, $column));
    }

    public static function expectsSingleValue(string $column): self
    {
        return new self(message: sprintf('The filter on "%s" needs a single value.', $column));
    }

    public static function expectsList(string $column, int $max): self
    {
        return new self(message: sprintf('The filter on "%s" needs a list of 1 to %d values.', $column, $max));
    }

    public static function expectsRange(string $column): self
    {
        return new self(message: sprintf('The filter on "%s" needs a list of two dates.', $column));
    }

    public static function rangeOutOfOrder(string $column): self
    {
        return new self(message: sprintf('The first date of the filter on "%s" cannot be after the second.', $column));
    }

    public static function expectsDays(string $column, int $max): self
    {
        return new self(message: sprintf('The filter on "%s" needs a number of days between 0 and %d.', $column, $max));
    }

    public static function invalidText(string $column, int $maxLength): self
    {
        return new self(message: sprintf('The filter on "%s" needs text of 1 to %d characters.', $column, $maxLength));
    }

    /**
     * @param list<string> $choices
     */
    public static function invalidChoice(string $column, array $choices): self
    {
        return new self(message: sprintf('The filter on "%s" accepts only: %s.', $column, implode(separator: ', ', array: $choices)));
    }

    public static function invalidIdentifier(string $column): self
    {
        return new self(message: sprintf('The filter on "%s" needs positive integer ids.', $column));
    }

    public static function invalidDate(string $column): self
    {
        return new self(message: sprintf('The filter on "%s" needs dates in the Y-m-d format.', $column));
    }
}
