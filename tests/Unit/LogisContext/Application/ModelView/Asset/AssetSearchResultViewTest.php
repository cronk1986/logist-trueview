<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\Asset;

use Logis\LogisContext\Application\ModelView\Asset\AssetSearchResultView;
use PHPUnit\Framework\TestCase;

final class AssetSearchResultViewTest extends TestCase
{
    public function test_it_renders_the_search_fields_of_an_asset(): void
    {
        $view = AssetSearchResultView::from(id: 77, serialNumber: 'ZB-00931', manufacturer: 'Zebra', model: 'ZT410', itemName: 'Label printer', status: 'active');

        $this->assertSame([
            'id' => 77,
            'serial_number' => 'ZB-00931',
            'manufacturer' => 'Zebra',
            'model' => 'ZT410',
            'item_name' => 'Label printer',
            'status' => 'active',
        ], $view->toArray());
    }
}
