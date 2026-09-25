<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\Location;

use Logis\LogisContext\Application\ModelView\Location\LocationSearchResultView;
use PHPUnit\Framework\TestCase;

final class LocationSearchResultViewTest extends TestCase
{
    public function test_it_renders_the_search_fields_of_a_location(): void
    {
        $view = LocationSearchResultView::from(id: 9, title: 'Austin Warehouse', city: 'Austin', state: 'Texas', postalCode: '73301');

        $this->assertSame(['id' => 9, 'title' => 'Austin Warehouse', 'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '73301'], $view->toArray());
    }

    public function test_the_address_fields_can_be_empty(): void
    {
        $view = LocationSearchResultView::from(id: 9, title: 'Austin Warehouse', city: null, state: null, postalCode: null);

        $this->assertSame(['id' => 9, 'title' => 'Austin Warehouse', 'city' => null, 'state' => null, 'postal_code' => null], $view->toArray());
    }
}
