<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\Search;

use Logis\LogisContext\Application\ModelView\Search\EntitySearchResultView;
use Logis\LogisContext\Application\ModelView\Search\EntitySearchResult;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;
use PHPUnit\Framework\TestCase;

final class EntitySearchResultViewTest extends TestCase
{
    public function test_it_has_more_when_the_total_exceeds_the_limit(): void
    {
        $result = EntitySearchResult::from(results: [['id' => 3]], total: 2);

        $view = EntitySearchResultView::from(result: $result, limit: SearchLimit::fromInt(value: 1));

        $this->assertSame(['results' => [['id' => 3]], 'total' => 2, 'has_more' => true], $view->toArray());
    }

    public function test_it_has_no_more_when_the_limit_covers_the_total(): void
    {
        $view = EntitySearchResultView::from(result: EntitySearchResult::empty(), limit: SearchLimit::fromInt(value: 5));

        $this->assertSame(['results' => [], 'total' => 0, 'has_more' => false], $view->toArray());
    }
}
