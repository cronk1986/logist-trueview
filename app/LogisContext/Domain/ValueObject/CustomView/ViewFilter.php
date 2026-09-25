<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class ViewFilter
{
    /**
     * @param scalar|null|list<scalar> $value
     */
    private function __construct(
        private string $column,
        private string $operator,
        private string|int|float|bool|array|null $value,
    ) {
    }

    /**
     * @param array<mixed> $filter
     */
    public static function fromArray(array $filter): self
    {
        $column = self::requiredText(filter: $filter, key: 'column');
        $operator = self::requiredText(filter: $filter, key: 'operator');

        if (!array_key_exists(key: 'value', array: $filter)) {
            throw InvalidViewFilterException::missing(key: 'value');
        }

        return new self(column: $column, operator: $operator, value: self::filterValue(value: $filter['value'], column: $column));
    }

    public function column(): string
    {
        return $this->column;
    }

    public function operator(): string
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
        return ['column' => $this->column, 'operator' => $this->operator, 'value' => $this->value];
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
