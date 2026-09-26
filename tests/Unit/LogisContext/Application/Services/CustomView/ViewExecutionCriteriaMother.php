<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewDefinition;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldCatalog;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewPageSize;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewSort;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;

final class ViewExecutionCriteriaMother
{
    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    public static function create(
        ViewType $type,
        VisibleCustomers $customers,
        array $columns = ['id'],
        array $filters = [],
        ?string $sortField = null,
        string $sortDirection = 'desc',
        int $pageSize = ViewPageSize::DEFAULT,
        ?string $cursor = null,
        ?string $term = null,
    ): ViewExecutionCriteria {
        return new ViewExecutionCriteria(
            definition: ViewDefinition::from(
                type: $type,
                columns: ViewColumns::fromList(columns: $columns),
                filters: ViewFilters::fromList(filters: $filters),
            ),
            sort: self::sort(type: $type, field: $sortField, direction: $sortDirection),
            pageSize: ViewPageSize::fromInt(value: $pageSize),
            cursor: $cursor,
            term: $term === null ? null : SearchTerm::fromString(value: $term),
            customers: $customers,
        );
    }

    private static function sort(ViewType $type, ?string $field, string $direction): ViewSort
    {
        if ($field === null) {
            return ViewSort::default();
        }

        return ViewSort::from(catalog: ViewFieldCatalog::for(type: $type), field: $field, direction: $direction);
    }
}
