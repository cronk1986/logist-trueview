<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewColumnsException;

final readonly class ViewColumns
{
    /**
     * @param list<string> $columns
     */
    private function __construct(private array $columns)
    {
    }

    /**
     * @param array<mixed> $columns
     */
    public static function fromList(array $columns): self
    {
        if ($columns === []) {
            throw InvalidViewColumnsException::empty();
        }

        $names = [];

        foreach ($columns as $column) {
            $name = self::columnName(column: $column);

            if (in_array(needle: $name, haystack: $names, strict: true)) {
                throw InvalidViewColumnsException::duplicated(column: $name);
            }

            $names[] = $name;
        }

        return new self(columns: $names);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->columns;
    }

    private static function columnName(mixed $column): string
    {
        if (!is_string(value: $column) || trim(string: $column) === '') {
            throw InvalidViewColumnsException::notAColumnName();
        }

        return trim(string: $column);
    }
}
