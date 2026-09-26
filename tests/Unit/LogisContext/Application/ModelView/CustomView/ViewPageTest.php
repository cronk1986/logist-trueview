<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use PHPUnit\Framework\TestCase;

final class ViewPageTest extends TestCase
{
    public function test_it_round_trips_its_plain_array_form(): void
    {
        $page = ViewPage::from(rows: [['id' => 1, 'location' => ['id' => 3, 'label' => 'Main'], 'contract' => null]], nextCursor: 'abc');

        $copy = ViewPage::fromArray(values: $page->toArray());

        $this->assertSame($page->rows(), $copy->rows());
        $this->assertSame('abc', $copy->nextCursor());
    }

    public function test_an_empty_page_has_no_rows_and_no_next_cursor(): void
    {
        $this->assertSame(['rows' => [], 'next_cursor' => null], ViewPage::empty()->toArray());
    }
}
