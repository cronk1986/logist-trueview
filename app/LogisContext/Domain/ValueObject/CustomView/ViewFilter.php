<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class ViewFilter
{
    private const COLUMN = 'column';
    private const OPERATOR = 'operator';
    private const VALUE = 'value';

    /**
     * @param scalar|null|list<scalar> $value
     */
    private function __construct(
        private string $column,
        private FilterOperator $operator,
        private string|int|float|bool|array|null $value,
    ) {
    }

    /**
     * @param array<mixed> $filter
     */
    public static function fromArray(array $filter): self
    {
        $column = self::requiredText(filter: $filter, key: self::COLUMN);
        $operator = self::operatorOf(filter: $filter, column: $column);

        if (!array_key_exists(key: self::VALUE, array: $filter)) {
            throw InvalidViewFilterException::missing(key: self::VALUE);
        }

        return new self(
            column: $column,
            operator: $operator,
            value: self::filterValue(
                value: $filter[self::VALUE],
                column: $column
            )
        );
    }

    /**
     * @return list<scalar>
     */
    public function values(): array
    {
        $value = $this->value;

        if (is_array(value: $value)) {
            return $value;
        }

        if ($value === null) {
            return [];
        }

        return [$value];
    }

    public function column(): string
    {
        return $this->column;
    }

    public function operator(): FilterOperator
    {
        return $this->operator;
    }

    /**
     * @return scalar|null|list<scalar>
     */
    public function value(): string|int|float|bool|array|null
    {
        return $this->value;
    }

    /**
     * @return array{column: string, operator: string, value: scalar|null|list<scalar>}
     */
    public function toArray(): array
    {
        return [self::COLUMN => $this->column, self::OPERATOR => $this->operator->value, self::VALUE => $this->value];
    }

    /**
     * @param array<mixed> $filter
     */
    private static function operatorOf(array $filter, string $column): FilterOperator
    {
        $name = self::requiredText(filter: $filter, key: self::OPERATOR);
        $operator = FilterOperator::tryFrom(value: $name);

        if ($operator === null) {
            throw InvalidViewFilterException::unknownOperator(column: $column, operator: $name);
        }

        return $operator;
    }

    /**
     * @param array<mixed> $filter
     */
    private static function requiredText(array $filter, string $key): string
    {
        $text = $filter[$key] ?? null;

        if (!is_string(value: $text) || trim(string: $text) === '') {
            throw InvalidViewFilterException::missing(key: $key);
        }

        return trim(string: $text);
    }

    /**
     * @return scalar|null|list<scalar>
     */
    private static function filterValue(mixed $value, string $column): string|int|float|bool|array|null
    {
        if ($value === null || is_scalar(value: $value)) {
            return $value;
        }

        if (!is_array(value: $value) || !array_is_list(array: $value)) {
            throw InvalidViewFilterException::invalidValue(column: $column);
        }

        foreach ($value as $item) {
            if (!is_scalar(value: $item)) {
                throw InvalidViewFilterException::invalidValue(column: $column);
            }
        }

        return $value;
    }
}
