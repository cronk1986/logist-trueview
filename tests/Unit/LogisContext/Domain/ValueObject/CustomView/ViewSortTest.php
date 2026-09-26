<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewSortException;
use Logis\LogisContext\Domain\ValueObject\CustomView\SortDirection;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldCatalog;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewSort;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\TestCase;

final class ViewSortTest extends TestCase
{
    public function test_the_default_sort_is_newest_id_first(): void
    {
        $sort = ViewSort::default();

        $this->assertSame('id', $sort->field());
        $this->assertSame(SortDirection::Desc, $sort->direction());
    }

    public function test_it_sorts_by_a_sortable_field(): void
    {
        $sort = ViewSort::from(catalog: ViewFieldCatalog::for(type: ViewType::MyAssets), field: 'created_at', direction: 'asc');

        $this->assertSame('created_at', $sort->field());
        $this->assertSame(SortDirection::Asc, $sort->direction());
    }

    public function test_it_rejects_a_field_that_is_not_sortable(): void
    {
        $this->expectException(InvalidViewSortException::class);
        $this->expectExceptionMessage('my_assets views cannot be sorted by "location".');

        ViewSort::from(catalog: ViewFieldCatalog::for(type: ViewType::MyAssets), field: 'location', direction: 'asc');
    }

    public function test_it_rejects_an_unknown_field(): void
    {
        $this->expectException(InvalidViewSortException::class);

        ViewSort::from(catalog: ViewFieldCatalog::for(type: ViewType::MyLocations), field: 'city', direction: 'asc');
    }

    public function test_it_rejects_an_unknown_direction(): void
    {
        $this->expectException(InvalidViewSortException::class);

        ViewSort::from(catalog: ViewFieldCatalog::for(type: ViewType::MyAssets), field: 'id', direction: 'up');
    }
}
