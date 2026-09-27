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
            self::ensureFitsItsUserFilter(filters: $filters, filter: $filter);
        }

        return new self(catalog: $catalog, columns: $columns, filters: $filters);
    }

    public function refinedBy(UserFilters $filters): self
    {
        $effective = $this->filters;

        foreach ($filters->byField() as $field => $requested) {
            $this->ensureCanBeFilteredBy(field: $field, requested: $requested);
            $effective = $effective->replacing(field: $field, filters: $requested);
        }

        return new self(catalog: $this->catalog, columns: $this->columns, filters: $effective);
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

    /**
     * @param list<ViewFilter> $requested
     */
    private function ensureCanBeFilteredBy(string $field, array $requested): void
    {
        $catalogField = $this->catalog->find(name: $field);

        if ($catalogField === null) {
            throw InvalidViewFilterException::unknownField(column: $field, viewType: $this->type()->value);
        }

        foreach ($requested as $filter) {
            $catalogField->ensureAccepts(filter: $filter);
        }

        $mode = $this->filters->modeOf(field: $field);

        if ($mode === null) {
            $this->ensureIsAColumn(field: $field);

            return;
        }

        if (!$mode->allowsUserFilter()) {
            throw InvalidViewFilterException::locked(column: $field);
        }

        if ($mode->requiresDiscreteValues()) {
            $this->ensureWithinSavedValues(field: $field, requested: $requested);
        }
    }

    private function ensureIsAColumn(string $field): void
    {
        if (!in_array(needle: $field, haystack: $this->columns->toArray(), strict: true)) {
            throw InvalidViewFilterException::notFilterable(column: $field);
        }
    }

    /**
     * @param list<ViewFilter> $requested
     */
    private function ensureWithinSavedValues(string $field, array $requested): void
    {
        $allowed = $this->filters->of(field: $field)[0]->values();

        foreach ($requested as $filter) {
            if (!$filter->operator()->isDiscrete()) {
                throw InvalidViewFilterException::restrictedNeedsDiscreteValues(column: $field);
            }

            foreach ($filter->values() as $value) {
                if (!in_array(needle: $value, haystack: $allowed, strict: true)) {
                    throw InvalidViewFilterException::outsideRestrictedValues(column: $field, values: $allowed);
                }
            }
        }
    }

    private static function ensureFitsItsUserFilter(ViewFilters $filters, ViewFilter $filter): void
    {
        $column = $filter->column();
        $mode = $filters->modeOf(field: $column);

        if ($mode === null || !$mode->requiresDiscreteValues()) {
            return;
        }

        if (!$filter->operator()->isDiscrete()) {
            throw InvalidViewFilterException::restrictedNeedsDiscreteValues(column: $column);
        }

        if (count(value: $filters->of(field: $column)) > 1) {
            throw InvalidViewFilterException::singleRestrictedFilter(column: $column);
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
