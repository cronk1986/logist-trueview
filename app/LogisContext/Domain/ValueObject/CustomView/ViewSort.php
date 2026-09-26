<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewSortException;

final readonly class ViewSort
{
    private const DEFAULT_FIELD = 'id';

    private function __construct(private string $field, private SortDirection $direction)
    {
    }

    public static function default(): self
    {
        return new self(field: self::DEFAULT_FIELD, direction: SortDirection::Desc);
    }

    public static function from(ViewFieldCatalog $catalog, string $field, string $direction): self
    {
        if ($catalog->findSortable(name: $field) === null) {
            throw InvalidViewSortException::notSortable(field: $field, viewType: $catalog->type()->value);
        }

        $sortDirection = SortDirection::tryFrom(value: $direction);

        if ($sortDirection === null) {
            throw InvalidViewSortException::unknownDirection(direction: $direction);
        }

        return new self(field: $field, direction: $sortDirection);
    }

    public function field(): string
    {
        return $this->field;
    }

    public function direction(): SortDirection
    {
        return $this->direction;
    }
}
