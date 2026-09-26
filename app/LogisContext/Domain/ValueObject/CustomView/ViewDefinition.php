<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewColumnsException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class ViewDefinition
{
    private function __construct(
        private ViewFieldCatalog $catalog,
        private ViewColumns $columns,
        private ViewFilters $filters,
    ) {
    }

    public static function from(ViewType $type, ViewColumns $columns, ViewFilters $filters): self
    {
        $catalog = ViewFieldCatalog::for(type: $type);

        foreach ($columns->toArray() as $column) {
            self::ensureColumnExists(catalog: $catalog, column: $column);
        }

        foreach ($filters->all() as $filter) {
            self::filteredField(catalog: $catalog, filter: $filter)->ensureAccepts(filter: $filter);
        }

        return new self(catalog: $catalog, columns: $columns, filters: $filters);
    }

    public function type(): ViewType
    {
        return $this->catalog->type();
    }

    public function catalog(): ViewFieldCatalog
    {
        return $this->catalog;
    }

    public function columns(): ViewColumns
    {
        return $this->columns;
    }

    public function filters(): ViewFilters
    {
        return $this->filters;
    }

    private static function ensureColumnExists(ViewFieldCatalog $catalog, string $column): void
    {
        if ($catalog->find(name: $column) === null) {
            throw InvalidViewColumnsException::unknown(column: $column, viewType: $catalog->type()->value);
        }
    }

    private static function filteredField(ViewFieldCatalog $catalog, ViewFilter $filter): ViewField
    {
        $field = $catalog->find(name: $filter->column());

        if ($field === null) {
            throw InvalidViewFilterException::unknownField(column: $filter->column(), viewType: $catalog->type()->value);
        }

        return $field;
    }
}
